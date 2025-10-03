<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\Product;
use App\Jobs\ProcessProductImport;
use Illuminate\Support\Facades\Log; // Added missing Log facade

class ProductImportController extends Controller
{
    /**
     * Display the product import form
     * 
     * This method renders the product import page using Inertia.js. It passes the 
     * authenticated user's information to the frontend if the user is logged in.
     * 
     * @return \Inertia\Response
     */
    public function showImportForm()
    {
        return Inertia::render('ProductImport', [
            'auth' => [
                'user' => auth()->user() ? [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ] : null
            ]
        ]);
    }

    /**
     * Process and validate the uploaded CSV file
     * 
     * This method handles the CSV file upload, validates it, stores it in the 
     * storage/app/public/imports directory, and dispatches a background job
     * to process the CSV asynchronously.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If file storage or processing fails
     */
    public function importCsv(Request $request)
    {
        try {
            $request->validate([
                'csv' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            ]);

            // Create the imports directory if it doesn't exist
            // This ensures we have a place to store uploaded CSV files
            if (!Storage::exists('public/imports')) {
                Storage::makeDirectory('public/imports');
            }

            $file = $request->file('csv');
            $originalName = $file->getClientOriginalName();
            $filename = 'imports/' . time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.csv';

            // Store the uploaded file with error handling
            // We use the original filename with a timestamp prefix to avoid collisions
            try {
                $path = $file->storeAs('imports', $filename, 'public');
                $fullPath = storage_path('app/public/' . $path);
            } catch (\Exception $e) {
                Log::error('Failed to store CSV file: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Failed to store CSV file',
                    'error' => $e->getMessage()
                ], 500);
            }

            // Dispatch job to process the CSV with the full path
            ProcessProductImport::dispatch($fullPath);

            return response()->json([
                'message' => 'CSV uploaded and processing has started',
                'path' => $path,
                'original_name' => $originalName
            ]);

        } catch (\Exception $e) {
            Log::error('CSV upload error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to process CSV upload',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle product image upload
     * 
     * This method processes image uploads for products. It validates the image,
     * creates a directory based on the product SKU if it doesn't exist, stores
     * the image with a unique filename, and updates the product record with 
     * the new image path if the product exists.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If image upload or processing fails
     */
    public function uploadImage(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB max
                'sku' => 'required|string|max:255',
            ]);

            $image = $request->file('image');
            $sku = $request->input('sku');

            // Sanitize the SKU to create a safe directory name
            // Removes any characters that aren't alphanumeric, hyphen, or underscore
            $safeSku = preg_replace('/[^a-zA-Z0-9-_]/', '', $sku);
            $directory = 'products/' . $safeSku;

            // Ensure the directory exists
            if (!Storage::exists('public/' . $directory)) {
                Storage::makeDirectory('public/' . $directory);
            }

            // Generate a unique filename
            $extension = $image->getClientOriginalExtension();
            $filename = $directory . '/' . Str::random(20) . '.' . $extension;

            // Store the image with proper error handling
            try {
                $path = $image->storeAs('public', $filename);
            } catch (\Exception $e) {
                Log::error('Failed to store image: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Failed to store image',
                    'error' => $e->getMessage()
                ], 500);
            }

            // If a product with the provided SKU exists, update its image information
            // This links the uploaded image to the product in the database
            try {
                $product = Product::where('sku', $sku)->first();
                if ($product) {
                    // If product exists, update its image path
                    $product->update([
                        'image_path' => $filename,
                        'image_url' => Storage::url($filename)
                    ]);
                }
            } catch (\Exception $e) {
                // Log the error but don't fail the upload
                Log::error('Failed to update product with image path: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Image uploaded successfully',
                'path' => $filename,
                'url' => Storage::url($filename),
                'sku' => $sku
            ]);

        } catch (\Exception $e) {
            Log::error('Image upload error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to process image upload',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
