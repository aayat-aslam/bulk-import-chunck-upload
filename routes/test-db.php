<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/test-db', function () {
    try {
        // Check if tables exist
        $tables = ['product_image', 'images', 'products', 'uploads'];
        $results = [];
        
        foreach ($tables as $table) {
            $results[$table] = [
                'exists' => \Schema::hasTable($table),
                'count' => \Schema::hasTable($table) ? DB::table($table)->count() : 0
            ];
        }
        
        // Get a sample of product_image records
        $sample = [];
        if ($results['product_image']['count'] > 0) {
            $sample['product_images'] = DB::table('product_image')
                ->select('product_image.*')
                ->limit(5)
                ->get()
                ->toArray();
                
            // Get related image and product info
            foreach ($sample['product_images'] as &$item) {
                $item->image = DB::table('images')->find($item->image_id);
                $item->product = DB::table('products')->find($item->product_id);
            }
        }
        
        return response()->json([
            'tables' => $results,
            'sample' => $sample
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});
