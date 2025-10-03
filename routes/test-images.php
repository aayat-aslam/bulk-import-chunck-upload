<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/test-images', function () {
    try {
        // Check images table
        $images = DB::table('images')
            ->select(['id', 'upload_id', 'variant', 'path', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        // Get upload info for each image
        foreach ($images as &$image) {
            $image->upload = DB::table('uploads')
                ->where('id', $image->upload_id)
                ->select(['id', 'upload_id', 'original_filename', 'status'])
                ->first();
        }
        
        return response()->json([
            'total_images' => DB::table('images')->count(),
            'recent_images' => $images
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});
