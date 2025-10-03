<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Upload;
use App\Models\Product;
use App\Jobs\ProcessUploadJob;

/**
 * Handles file uploads and processing for product images.
 *
 * This controller manages chunked file uploads, file assembly, and association
 * of uploaded images with products. It supports resumable uploads and includes
 * checksum verification for data integrity.
 */

class UploadController extends Controller
{
    /**
     * The base path for storing uploaded files.
     *
     * @var string
     */
    protected $uploadPath = 'app/uploads';

    /**
     * Handle a single chunk of a file upload.
     *
     * This method receives individual chunks of a file, verifies their integrity,
     * and stores them temporarily until all chunks are received.
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function uploadChunk(Request $req)
    {
        $req->validate([
            'upload_id'=>'required|uuid',
            'chunk_index'=>'required|integer|min:0',
            'total_chunks'=>'required|integer|min:1',
            'chunk'=>'required|file',
            'chunk_checksum'=>'required|string'
        ]);

        $uploadId = $req->input('upload_id');
        $chunkIndex = $req->input('chunk_index');
        $totalChunks = $req->input('total_chunks');

        $chunk = $req->file('chunk');
        $chunkContents = file_get_contents($chunk->getRealPath());
        $md5 = md5($chunkContents);
        if ($md5 !== $req->input('chunk_checksum')) {
            return response()->json(['error'=>'chunk checksum mismatch'],422);
        }

        $tmpDir = storage_path("app/uploads/tmp/{$uploadId}");
        if (!is_dir($tmpDir)) mkdir($tmpDir,0755,true);

        // Save chunk (overwrite if re-sent; idempotent)
        $chunkPath = "{$tmpDir}/chunk_{$chunkIndex}.part";
        file_put_contents($chunkPath, $chunkContents, LOCK_EX);

        // Create Upload record if not exists
        $upload = Upload::firstOrCreate(['upload_id'=>$uploadId], [
            'original_filename' => $req->input('file_name', $chunk->getClientOriginalName()),
            'file_size' => $req->input('file_size', null),
            'status' => 'uploading',
        ]);

        // store received chunk count maybe in meta
        return response()->json(['status'=>'ok','received_chunk'=>$chunkIndex]);
    }

    /**
     * Complete a chunked file upload by assembling all received chunks.
     *
     * This method combines all uploaded chunks into a single file, verifies
     * the complete file's checksum, and dispatches a job to process the upload.
     *
     * @param  Request  $req
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws \Exception If file assembly fails
     */
    public function completeUpload(Request $req) : JsonResponse
    {
        $req->validate(['upload_id'=>'required|uuid','file_checksum'=>'required|string']);
        $uploadId = $req->input('upload_id');
        $fileChecksum = $req->input('file_checksum');

        $upload = Upload::where('upload_id',$uploadId)->lockForUpdate()->firstOrFail();
        $tmpDir = storage_path("app/uploads/tmp/{$uploadId}");

        // assemble
        $chunks = glob("{$tmpDir}/chunk_*.part");
        if (empty($chunks)) return response()->json(['error'=>'no_chunks'],422);

        // ensure contiguous and sorted
        natsort($chunks);
        $assembledPath = storage_path("app/uploads/{$uploadId}_assembled");
        $out = fopen($assembledPath,'wb');
        if (!$out) return response()->json(['error'=>'cannot_assemble'],500);

        foreach ($chunks as $c) {
            $data = file_get_contents($c);
            // Optionally verify each chunk checksum stored earlier; omitted here for brevity.
            fwrite($out, $data);
        }
        fclose($out);

        $computed = md5_file($assembledPath);
        if ($computed !== $fileChecksum) {
            // keep chunks for retry; mark failed
            $upload->update(['status'=>'failed']);
            unlink($assembledPath);
            return response()->json(['error'=>'checksum_mismatch'],422);
        }

        // Save to public storage using Laravel's storage facade
        $storagePath = 'uploads/' . $uploadId . '/original';
        
        // Use Laravel's Storage to handle the file
        try {
            // Create the directory if it doesn't exist
            Storage::makeDirectory(dirname($storagePath));
            
            // Move the file using Storage facade
            Storage::putFileAs(
                dirname($storagePath),
                new \Illuminate\Http\File($assembledPath),
                basename($storagePath)
            );
            
            // Get the full path for reference
            $fullStoragePath = Storage::path($storagePath);
            
            Log::info('File saved successfully', [
                'path' => $storagePath,
                'full_path' => $fullStoragePath,
                'file_exists' => Storage::exists($storagePath) ? 'yes' : 'no',
                'file_size' => Storage::exists($storagePath) ? Storage::size($storagePath) : 0,
                'url' => Storage::url($storagePath)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to save file', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
        
        // Verify the file was created and is accessible
        if (!Storage::exists($storagePath)) {
            throw new \Exception('File was not saved to the expected location: ' . $storagePath);
        }

        // Set proper permissions
        chmod($fullStoragePath, 0644);

        DB::transaction(function() use ($upload, $storagePath, $fullStoragePath, $fileChecksum) {
            // Update the upload record with the correct path and status
            // Using 'uploading' status as it's one of the allowed ENUM values in the database
            $upload->update([
                'status' => 'uploading', // Using 'uploading' as it's one of the allowed ENUM values
                'file_checksum' => $fileChecksum,
                'file_size' => filesize($fullStoragePath),
                'path' => $storagePath  // Store the relative path in the database
            ]);

            // Dispatch the job to process the upload and create variants
            ProcessUploadJob::dispatch($upload->id, $fullStoragePath);

            // Log before dispatching job
            Log::info('Dispatching ProcessUploadJob', [
                'upload_id' => $upload->id,
                'file_path' => $fullStoragePath,
                'storage_path' => $storagePath,
                'file_exists' => file_exists($fullStoragePath) ? 'yes' : 'no',
                'file_size' => filesize($fullStoragePath)
            ]);

            // Log the final storage path
            Log::info('File moved to storage', [
                'upload_id' => $upload->id,
                'path' => $storagePath,
                'full_path' => $fullStoragePath,
                'file_size' => filesize($fullStoragePath),
                'file_exists' => file_exists($fullStoragePath) ? 'yes' : 'no'
            ]);

            // Dispatch processing job with the correct path
            $jobId = (string) \Illuminate\Support\Str::uuid();
            $job = new ProcessUploadJob($upload->id, $fullStoragePath, $jobId);

            dispatch($job);

            Log::info('ProcessUploadJob dispatched', [
                'upload_id' => $upload->id,
                'job_id' => $jobId,
                'file_path' => $fullStoragePath
            ]);
        });

        // remove tmp chunks (background queue might still need file - careful)
        // we keep them for a short time. Optionally delete.
        return response()->json(['status'=>'assembled','upload_id'=>$uploadId]);
    }

    /**
     * Attach an uploaded image to a product.
     *
     * This method associates an uploaded image with a product and handles
     * setting the primary image if specified.
     *
     * @param  Request  $req
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     */
    /**
     * Attach an uploaded image to a product.
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\JsonResponse
     */
    public function attachToProduct(Request $req): JsonResponse
    {
        try {
            // Validate the request
            $validated = $req->validate([
                'upload_id' => 'required|uuid|exists:uploads,upload_id',
                'sku' => 'required|string|exists:products,sku',
                'is_primary' => 'sometimes|boolean'
            ]);

            // Log the request
            Log::info('Attaching image to product', [
                'upload_id' => $validated['upload_id'],
                'sku' => $validated['sku'],
                'is_primary' => $validated['is_primary'] ?? false
            ]);

            // Find the upload and product
            $upload = Upload::where('upload_id', $validated['upload_id'])->firstOrFail();
            $product = Product::where('sku', $validated['sku'])->firstOrFail();

            // Check if upload is still uploading
            if ($upload->status === 'uploading') {
                // Check if processing is taking too long (more than 30 seconds)
                $processingTime = now()->diffInSeconds($upload->updated_at);
                $maxProcessingTime = 30; // seconds

                if ($processingTime > $maxProcessingTime) {
                    // If processing is taking too long, mark as failed
                    $upload->status = 'failed';
                    $upload->save();
                    throw new \Exception('Image upload is taking too long. Please try uploading again.');
                }

                return response()->json([
                    'status' => 'uploading',
                    'message' => 'Image is still being uploaded. Please try again in a moment.',
                    'processing_time' => $processingTime
                ], 202); // 202 Accepted
            }

            // Check if upload failed
            if ($upload->status !== 'complete') {
                throw new \Exception('Upload processing failed. Please try uploading the image again.');
            }

            // Get all images for this upload
            $allImages = $upload->images()->get();

            // Log all available images for this upload
            Log::info('Available images for upload', [
                'upload_id' => $upload->id,
                'status' => $upload->status,
                'images_count' => $allImages->count(),
                'images' => $allImages->toArray()
            ]);

            // If no images but status is complete, there might be a race condition
            if ($allImages->isEmpty()) {
                // Check if we have a file in storage that wasn't processed
                $potentialPaths = [
                    storage_path("app/public/uploads/{$upload->upload_id}/original"), // New path format
                    storage_path("app/uploads/{$upload->upload_id}_orig") // Old path format for backward compatibility
                ];

                $foundPath = null;
                foreach ($potentialPaths as $path) {
                    if (file_exists($path)) {
                        $foundPath = $path;
                        break;
                    }
                }

                if ($foundPath) {
                    Log::info('Found unprocessed original file, dispatching processing job', [
                        'upload_id' => $upload->id,
                        'file_path' => $foundPath,
                        'file_exists' => 'yes',
                        'file_size' => filesize($foundPath)
                    ]);

                    // Reset status to uploading to trigger reprocessing
                    $upload->update(['status' => 'uploading']);

                    // Ensure the directory exists for the new path format
                    $newDir = storage_path("app/public/uploads/{$upload->upload_id}");
                    if (!file_exists($newDir)) {
                        mkdir($newDir, 0755, true);
                    }

                    // Copy the file to the new location if it's in the old location
                    if ($foundPath === $potentialPaths[1]) { // If using old path
                        $newPath = $potentialPaths[0];
                        copy($foundPath, $newPath);
                        $foundPath = $newPath;
                    }

                    // Dispatch the job again with the file path
                    ProcessUploadJob::dispatch($upload->id, $foundPath);

                    return response()->json([
                        'status' => 'processing',
                        'message' => 'Processing original image. Please try again in a moment.'
                    ], 202);
                }

                // If we get here, there are no images and no original file to process
                $upload->update(['status' => 'failed']);
                throw new \Exception('No image files found for this upload. Please try uploading the image again.');
            }

            // Get all images for this upload for debugging
            $allImages = $upload->images()->get();

            // Log all available images for this upload
            Log::info('Available images for upload', [
                'upload_id' => $upload->id,
                'status' => $upload->status,
                'images_count' => $allImages->count(),
                'images' => $allImages->map(function($img) {
                    return [
                        'id' => $img->id,
                        'variant' => $img->variant,
                        'path' => $img->path,
                        'created_at' => $img->created_at
                    ];
                })
            ]);

            // Try to find the original image first, then fall back to any variant
            $image = $upload->images()->where('variant', 'original')->first();

            if (!$image) {
                // If no original image is found, try to find any variant
                $image = $upload->images()->first();

                if ($image) {
                    Log::warning('Using non-original image variant for upload', [
                        'upload_id' => $upload->id,
                        'variant_used' => $image->variant,
                        'image_id' => $image->id
                    ]);
                } else {
                    // Log detailed error information
                    Log::error('No image variants found for upload', [
                        'upload_id' => $upload->id,
                        'upload_status' => $upload->status,
                        'created_at' => $upload->created_at,
                        'updated_at' => $upload->updated_at
                    ]);

                    throw new \Exception('No image variants found for this upload. Please try uploading the image again.');
                }
            }

            // Check if already attached
            $existingRecord = DB::table('product_image')
                ->where('product_id', $product->id)
                ->where('image_id', $image->id)
                ->first();

            DB::beginTransaction();

            try {
                if ($existingRecord) {
                    Log::info('Image already attached to product, updating if needed', [
                        'product_id' => $product->id,
                        'image_id' => $image->id
                    ]);

                    // If marked as primary, update other records
                    if ($req->boolean('is_primary')) {
                        DB::table('product_image')
                            ->where('product_id', $product->id)
                            ->update(['is_primary' => false]);

                        DB::table('product_image')
                            ->where('product_id', $product->id)
                            ->where('image_id', $image->id)
                            ->update(['is_primary' => true]);

                        $product->update(['primary_image_id' => $image->id]);

                        Log::info('Updated existing image as primary', [
                            'product_id' => $product->id,
                            'image_id' => $image->id
                        ]);
                    }

                    DB::commit();
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Image attachment updated',
                        'image_id' => $image->id,
                        'product_id' => $product->id,
                        'is_primary' => $req->boolean('is_primary')
                    ]);
                }

                // Insert new record if not exists
                $result = DB::table('product_image')->insert([
                    'product_id' => $product->id,
                    'image_id' => $image->id,
                    'is_primary' => $req->boolean('is_primary') ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                if (!$result) {
                    throw new \Exception('Failed to insert into product_image table');
                }

                // If set as primary, update other records
                if ($req->boolean('is_primary')) {
                    DB::table('product_image')
                        ->where('product_id', $product->id)
                        ->where('image_id', '!=', $image->id)
                        ->update(['is_primary' => false]);

                    $product->update(['primary_image_id' => $image->id]);
                }

                DB::commit();

                Log::info('Successfully attached image to product', [
                    'product_id' => $product->id,
                    'image_id' => $image->id,
                    'is_primary' => $req->boolean('is_primary')
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Image attached to product',
                    'image_id' => $image->id,
                    'product_id' => $product->id,
                    'is_primary' => $req->boolean('is_primary')
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e; // Re-throw to be caught by the outer catch
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in attachToProduct', [
                'errors' => $e->errors(),
                'input' => $req->all()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error in attachToProduct', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $req->except(['file']) // Don't log file content
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while attaching image to product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkImageReady(Request $req): JsonResponse
    {
        $req->validate(['upload_id' => 'required|uuid']);
        $upload = Upload::where('upload_id', $req->upload_id)->firstOrFail();
        $imageExists = $upload->images()->where('variant', 'original')->exists();

        return response()->json(['ready' => $imageExists]);
    }

    /**
     * Get the status of an ongoing or completed upload.
     *
     * @param  string  $uploadId
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadStatus($uploadId)
    {
        $upload = Upload::where('upload_id', $uploadId)->first();

        if (!$upload) {
            return response()->json(['error' => 'Upload not found', 'status' => 'not_found'], 404);
        }

        // Return relevant status info, e.g.:
        return response()->json([
            'upload_id' => $upload->upload_id,
            'status' => $upload->status,
            'file_size' => $upload->file_size,
            'file_checksum' => $upload->file_checksum,
            // Add other info as needed
        ]);
    }

}

