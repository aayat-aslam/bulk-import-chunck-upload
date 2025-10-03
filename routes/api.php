<?php

use App\Http\Controllers\UploadController;
use App\Jobs\ProcessUploadJob;
use App\Models\Upload;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// Test route to manually trigger the ProcessUploadJob
Route::get('test-upload-job', function () {
    // Create a test upload record
    $upload = Upload::create([
        'status' => 'uploading',
        'original_filename' => 'test-image.jpg',
        'file_size' => 0,
        'file_checksum' => '',
        'meta' => []
    ]);
    
    // Path to a test image (you may need to adjust this path)
    $testImagePath = storage_path('app/public/test-image.jpg');
    
    if (!file_exists($testImagePath)) {
        // Create a simple test image if it doesn't exist
        $image = imagecreatetruecolor(100, 100);
        $bgColor = imagecolorallocate($image, 255, 0, 0); // Red background
        imagefill($image, 0, 0, $bgColor);
        imagejpeg($image, $testImagePath, 90);
        imagedestroy($image);
    }
    
    // Log the test job dispatch
    Log::info('Dispatching test ProcessUploadJob', [
        'upload_id' => $upload->id,
        'test_image_path' => $testImagePath,
        'file_exists' => file_exists($testImagePath) ? 'yes' : 'no'
    ]);
    
    // Dispatch the job
    ProcessUploadJob::dispatch($upload->id, $testImagePath);
    
    return response()->json([
        'message' => 'Test job dispatched',
        'upload_id' => $upload->id,
        'test_image_path' => $testImagePath
    ]);
});

// File Upload Routes - Using web middleware for CSRF protection and session
Route::middleware('web')->group(function () {
    Route::prefix('upload')->group(function () {
        Route::post('/chunk', [UploadController::class, 'uploadChunk']);
        Route::post('/complete', [UploadController::class, 'completeUpload']);
        Route::post('/attach-to-product', [UploadController::class, 'attachToProduct']);
        Route::get('/{uploadId}/status', [UploadController::class, 'uploadStatus']); // Main status endpoint
        Route::get('/{uploadId}/ready', [UploadController::class, 'checkImageReady']); // Separate endpoint for image ready check
    });
});
