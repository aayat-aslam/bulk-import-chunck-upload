<?php

namespace App\Jobs;

use App\Models\Image;
use App\Models\Upload;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Interfaces\ImageInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;


/**
 * Processes an uploaded image file by creating multiple variants at different sizes.
 *
 * This job is responsible for:
 * - Processing the original uploaded file
 * - Generating resized variants of the image
 * - Storing all variants in the appropriate storage location
 * - Creating database records for each image variant
 * - Updating the upload status upon completion
 */

class ProcessUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 300; // 5 minutes

    /**
     * The target sizes for image variants.
     * Maps variant names to their target sizes in pixels.
     *
     * @var array
     */
    protected array $variants = [
        Image::VARIANT_THUMBNAIL => 256,
        Image::VARIANT_MEDIUM => 512,
        Image::VARIANT_LARGE => 1024,
    ];

    /**
     * Create a new job instance.
     *
     * @param int $uploadId The ID of the upload record
     * @param string $filePath Path to the uploaded file
     * @param string|null $jobId Optional job ID for tracking
     */
    public function __construct(
        public int $uploadId,
        public string $filePath,
        public ?string $jobId = null
    ) {}

    /**
     * Execute the job.
     *
     * Processes the uploaded image by creating multiple variants at different sizes.
     * Updates the upload status upon completion.
     *
     * @return void
     * @throws \Exception If image processing fails
     */
    public function handle()
    {
        try {
            // Find the upload record or fail if not found
            $upload = Upload::find($this->uploadId);
            if (!$upload) {
                throw new \Exception("Upload record not found for ID: " . $this->uploadId);
            }

            // Log the start of processing with detailed information
            Log::info('Starting ProcessUploadJob', [
                'upload_id' => $upload->id,
                'upload_uuid' => $upload->upload_id,
                'file_path' => $this->filePath,
                'file_exists' => file_exists($this->filePath) ? 'yes' : 'no',
                'file_size' => file_exists($this->filePath) ? filesize($this->filePath) : 0,
                'current_status' => $upload->status,
                'php_version' => phpversion(),
                'laravel_version' => app()->version(),
                'memory_limit' => ini_get('memory_limit')
            ]);

            // Verify the file exists and is readable
            if (!file_exists($this->filePath)) {
                throw new \Exception(sprintf(
                    'File does not exist: %s. Current working directory: %s',
                    $this->filePath,
                    getcwd()
                ));
            }

            if (!is_readable($this->filePath)) {
                throw new \Exception(sprintf(
                    'File is not readable: %s. Permissions: %s',
                    $this->filePath,
                    substr(sprintf('%o', fileperms($this->filePath)), -4)
                ));
            }

            // Check if the file has content
            if (filesize($this->filePath) === 0) {
                throw new \Exception('File is empty: ' . $this->filePath);
            }

            // Verify GD is installed and working
            if (!extension_loaded('gd') || !function_exists('gd_info')) {
                throw new \Exception('GD library is not installed or not enabled');
            }

            // Update status to uploading
            $upload->update(['status' => 'uploading']);
            Log::info('Updated upload status to uploading', ['upload_id' => $upload->id]);

            // Define the target directory and file path
            $uploadDir = 'uploads/' . $upload->upload_id;
            $targetDir = storage_path('app/' . $uploadDir);
            
            Log::info('Storage paths', [
                'upload_id' => $upload->id,
                'base_path' => base_path(),
                'storage_path' => storage_path(),
                'target_dir' => $targetDir,
                'target_dir_exists' => file_exists($targetDir) ? 'yes' : 'no',
                'target_dir_writable' => is_writable(dirname($targetDir)) ? 'yes' : 'no',
                'storage_app_writable' => is_writable(storage_path('app')) ? 'yes' : 'no'
            ]);
            
            // Ensure the directory exists
            if (!file_exists($targetDir)) {
                Log::info('Creating target directory', ['path' => $targetDir]);
                if (!mkdir($targetDir, 0755, true)) {
                    $error = error_get_last();
                    throw new \Exception('Failed to create directory: ' . $targetDir . ' - ' . ($error['message'] ?? 'Unknown error'));
                }
                Log::info('Directory created', ['path' => $targetDir]);
            }
            
            // Get file extension from original file
            $extension = pathinfo($this->filePath, PATHINFO_EXTENSION);
            $targetFilename = 'original' . ($extension ? '.' . $extension : '');
            $targetPath = $targetDir . '/' . $targetFilename;
            $relativePath = 'app/uploads/' . $upload->upload_id . '/' . $targetFilename; // Updated relative path
            
            Log::info('Starting file storage process', [
                'upload_id' => $upload->id,
                'source_path' => $this->filePath,
                'target_dir' => $targetDir,
                'target_path' => $targetPath,
                'file_exists' => file_exists($this->filePath) ? 'yes' : 'no',
                'file_size' => file_exists($this->filePath) ? filesize($this->filePath) : 0
            ]);
            
            try {
                // Check if directory is writable
                if (!is_writable($targetDir)) {
                    throw new \Exception('Directory is not writable: ' . $targetDir . 
                                      ' (Permissions: ' . substr(sprintf('%o', fileperms($targetDir)), -4) . ')');
                }
                
                // Copy the file directly using PHP's copy function
                Log::info('Copying file', [
                    'source' => $this->filePath,
                    'source_exists' => file_exists($this->filePath) ? 'yes' : 'no',
                    'source_size' => file_exists($this->filePath) ? filesize($this->filePath) : 0,
                    'target' => $targetPath,
                    'target_dir' => dirname($targetPath),
                    'target_dir_writable' => is_writable(dirname($targetPath)) ? 'yes' : 'no'
                ]);
                
                // Verify source file exists and is readable
                if (!file_exists($this->filePath)) {
                    throw new \Exception('Source file does not exist: ' . $this->filePath);
                }
                
                if (!is_readable($this->filePath)) {
                    throw new \Exception('Source file is not readable: ' . $this->filePath);
                }
                
                // Ensure target directory exists and is writable
                if (!is_writable(dirname($targetPath))) {
                    throw new \Exception('Target directory is not writable: ' . dirname($targetPath));
                }
                
                // Try to copy the file
                $copyResult = @copy($this->filePath, $targetPath);
                
                if (!$copyResult) {
                    $error = error_get_last();
                    throw new \Exception(sprintf(
                        'Failed to copy file from %s to %s: %s',
                        $this->filePath,
                        $targetPath,
                        $error['message'] ?? 'Unknown error'
                    ));
                }
                
                // Verify the file was copied
                if (!file_exists($targetPath)) {
                    throw new \Exception('File was not created at expected location: ' . $targetPath);
                }
                
                if (filesize($targetPath) === 0) {
                    throw new \Exception('File was created but is empty: ' . $targetPath);
                }
                
                // Set proper permissions
                chmod($targetPath, 0644);
                
                Log::info('File saved successfully', [
                    'upload_id' => $upload->id,
                    'source' => $this->filePath,
                    'target' => $targetPath,
                    'file_size' => filesize($targetPath),
                    'permissions' => substr(sprintf('%o', fileperms($targetPath)), -4)
                ]);
                
                // Update the upload record with the new path (storing relative to storage directory)
                $upload->update([
                    'path' => $relativePath,
                    'status' => 'complete'
                ]);
                
                Log::info('Upload record updated', [
                    'upload_id' => $upload->id,
                    'path' => $relativePath,
                    'status' => 'complete',
                    'full_path' => $targetPath
                ]);
                
                // List directory contents for debugging
                $files = scandir($targetDir);
                Log::debug('Directory contents after copy', [
                    'directory' => $targetDir,
                    'files' => $files
                ]);
                
            } catch (\Exception $e) {
                Log::error('File storage failed', [
                    'upload_id' => $upload->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'directory_permissions' => file_exists($storageDirectory) ? substr(sprintf('%o', fileperms($storageDirectory)), -4) : 'not exists'
                ]);
                
                // Update upload status to failed
                $upload->update(['status' => 'failed']);
                
                throw $e;
            }

            // Log before storing
            Log::info('Processing original image', [
                'upload_id' => $upload->id,
                'upload_uuid' => $upload->upload_id,
                'file_path' => $this->filePath,
                'file_exists' => file_exists($this->filePath) ? 'yes' : 'no',
                'file_size' => file_exists($this->filePath) ? filesize($this->filePath) : 0,
                'mime_type' => $mime,
                'dimensions' => "{$originalW}x{$originalH}",
                'storage_disk' => 'public'
            ]);

            // Verify the file was copied
            if (!file_exists($fullPath)) {
                throw new \Exception("File copy failed. Target file does not exist: " . $fullPath);
            }

            if (filesize($fullPath) === 0) {
                throw new \Exception("File copy resulted in zero-byte file: " . $fullPath);
            }

            $originalChecksum = md5_file($fullPath);
            Log::info('File copied successfully', [
                'upload_id' => $upload->id,
                'file_path' => $fullPath,
                'file_size' => filesize($fullPath),
                'checksum' => $originalChecksum
            ]);

            // Create database record for the original image
            try {
                // First, check if an image record already exists for this upload and variant
                $image = Image::updateOrCreate(
                    [
                        'upload_id' => $upload->id,
                        'variant' => Image::VARIANT_ORIGINAL
                    ],
                    [
                        'path'      => $relativePath,
                        'mime'      => $mime,
                        'width'     => $originalW,
                        'height'    => $originalH,
                        'checksum'  => $originalChecksum,
                    ]
                );

                // Verify the image was saved
                $savedImage = Image::find($image->id);
                if (!$savedImage) {
                    throw new \Exception("Failed to save image record to database");
                }

                Log::info('Image record created/updated', [
                    'upload_id' => $upload->id,
                    'image_id' => $image->id,
                    'variant' => $image->variant,
                    'path' => $image->path,
                    'file_exists' => file_exists($fullPath) ? 'yes' : 'no'
                ]);

                // Log the created image
                Log::info('Created original image record', [
                    'upload_id' => $upload->id,
                    'image_id' => $image->id,
                    'path' => $image->path,
                    'variant' => $image->variant,
                    'checksum' => $originalChecksum
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create image record', [
                    'upload_id' => $upload->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

            // Create variants for each target size
            try {
                foreach ($this->variants as $variant => $size) {
                    $this->createImageVariant($fullPath, $this->uploadId, $storageBase, $variant, $size, $upload->upload_id);
                }

                // Update upload status to complete
                $upload->update([
                    'status' => 'complete',  // 'complete' is one of the allowed ENUM values
                    'file_size' => filesize($fullPath),
                    'path' => $relativePath
                ]);

                // Log all images associated with this upload
                $images = $upload->images()->get();
                
                // Log each image's details
                $imageDetails = [];
                foreach ($images as $img) {
                    $filePath = storage_path('app/public/' . ltrim($img->path, '/'));
                    $imageDetails[] = [
                        'id' => $img->id,
                        'variant' => $img->variant,
                        'path' => $img->path,
                        'file_exists' => file_exists($filePath) ? 'yes' : 'no',
                        'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
                        'created_at' => $img->created_at->toDateTimeString()
                    ];
                }

                Log::info('Successfully processed upload', [
                    'upload_id' => $upload->id,
                    'upload_uuid' => $upload->upload_id,
                    'status' => 'complete',
                    'image_count' => $images->count(),
                    'images' => $imageDetails
                ]);

            } catch (\Exception $e) {
                Log::error('Error creating image variants', [
                    'upload_id' => $upload->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            $errorMessage = 'ProcessUploadJob failed: ' . $e->getMessage();
            
            $logContext = [
                'upload_id' => $this->uploadId ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'file_exists' => isset($this->filePath) ? (file_exists($this->filePath) ? 'yes' : 'no') : 'unknown',
                'file_permissions' => isset($this->filePath) && file_exists($this->filePath) 
                    ? substr(sprintf('%o', fileperms($this->filePath)), -4)
                    : 'n/a',
                'storage_path' => storage_path(),
                'app_path' => app_path(),
                'current_working_dir' => getcwd(),
                'gd_info' => function_exists('gd_info') ? gd_info() : 'GD not available'
            ];
            
            Log::error($errorMessage, $logContext);
            
            // Update upload status to failed if we have an upload record
            if (isset($upload)) {
                $upload->update(['status' => 'failed']);
                Log::error('Marked upload as failed', ['upload_id' => $upload->id]);
            }
            
            // Re-throw the exception to mark the job as failed
            throw $e; // Re-throw to allow job retries
        }
    }

    /**
     * Create a resized variant of the original image.
     *
     * @param string $sourcePath Path to the source image
     * @param int $uploadId ID of the upload record
     * @param string $storageBase Base storage path
     * @param string $variant Variant name (e.g., '256', '512')
     * @param int $targetSize Target size (longest side)
     * @param string $uuid UUID of the upload
     * @return void
     * @throws \Exception If image processing fails
     */
    private function createImageVariant(
        string $sourcePath,
        int $uploadId,
        string $storageBase,
        string $variant,
        int $targetSize,
        string $uuid
    ): void
    {
        Log::info('Creating image variant', [
            'upload_id' => $uploadId,
            'variant' => $variant,
            'source_path' => $sourcePath,
            'target_size' => $targetSize,
            'uuid' => $uuid
        ]);

        // Verify source file exists and is readable
        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            throw new \Exception("Source file does not exist or is not readable: " . $sourcePath);
        }

        // Create a new image manager instance with GD driver
        $manager = new ImageManager(Driver::class);

        try {
            // Create a new image instance
            $image = $manager->read($sourcePath);
            
            // Get original dimensions for logging
            $originalWidth = $image->width();
            $originalHeight = $image->height();
            
            Log::debug('Original image loaded', [
                'upload_id' => $uploadId,
                'variant' => $variant,
                'original_dimensions' => "{$originalWidth}x{$originalHeight}",
                'target_size' => $targetSize
            ]);

            // Resize maintaining aspect ratio
            if ($originalWidth >= $originalHeight) {
                $image->resize($targetSize, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            } else {
                $image->resize(null, $targetSize, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            $newWidth = $image->width();
            $newHeight = $image->height();
            
            Log::debug('Image resized', [
                'upload_id' => $uploadId,
                'variant' => $variant,
                'new_dimensions' => "{$newWidth}x{$newHeight}"
            ]);

            // Define the storage path for this variant
            $variantFileName = $variant . '.jpg';
            $relativePath = 'uploads/' . $uuid . '/' . $variantFileName;
            $fullPath = storage_path('app/public/' . $relativePath);

            // Ensure the directory exists
            $directory = dirname($fullPath);
            if (!file_exists($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    throw new \Exception("Failed to create directory: " . $directory);
                }
                Log::info('Created directory for variant', [
                    'upload_id' => $uploadId,
                    'variant' => $variant,
                    'directory' => $directory
                ]);
            }

            // Save the resized image with quality setting
            $image->save($fullPath, quality: 90);

            // Verify the file was saved
            if (!file_exists($fullPath)) {
                throw new \Exception("Failed to save variant image to: " . $fullPath);
            }
            
            $fileSize = filesize($fullPath);
            if ($fileSize === 0) {
                throw new \Exception("Variant image was saved but has 0 bytes: " . $fullPath);
            }

            // Get MIME type from the saved file
            $mimeType = mime_content_type($fullPath);
            $checksum = md5_file($fullPath);
            
            Log::debug('Variant image saved', [
                'upload_id' => $uploadId,
                'variant' => $variant,
                'path' => $relativePath,
                'full_path' => $fullPath,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'checksum' => $checksum
            ]);

            // Create or update database record for the variant
            $imageRecord = Image::updateOrCreate(
                [
                    'upload_id' => $uploadId,
                    'variant' => $variant,
                ],
                [
                    'path' => $relativePath,
                    'mime' => $mimeType,
                    'width' => $newWidth,
                    'height' => $newHeight,
                    'checksum' => $checksum,
                ]
            );

            if (!$imageRecord->exists) {
                throw new \Exception("Failed to create/update image record for variant: " . $variant);
            }

            Log::info('Created/updated image variant record', [
                'upload_id' => $uploadId,
                'image_id' => $imageRecord->id,
                'variant' => $variant,
                'path' => $relativePath,
                'dimensions' => "{$newWidth}x{$newHeight}",
                'file_exists' => file_exists($fullPath) ? 'yes' : 'no'
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating image variant', [
                'upload_id' => $uploadId,
                'variant' => $variant,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
