<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductController;

Route::get('/uploads/{filename}', function ($filename) {
    $path = storage_path('app/uploads/' . $filename);

    if (!File::exists($path)) {
        abort(404, "File not found: " . $path);
    }

    if (!is_readable($path)) {
        abort(403, "Cannot access file. Check permissions for: " . $path);
    }

    $file = File::get($path);
    $type = File::mimeType($path);

    return Response::make($file, 200, [
        'Content-Type' => $type,
        'Content-Disposition' => 'inline'
    ]);
})->where('filename', '.*');

// Product Import Routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Product import
    Route::get('/products/import', [ProductImportController::class, 'showImportForm'])->name('products.import');
    Route::post('/api/products/import/csv', [ProductImportController::class, 'importCsv'])->name('api.products.import.csv');
    Route::post('/api/products/upload-image', [ProductImportController::class, 'uploadImage'])->name('api.products.upload.image');

    // File Uploader
    Route::get('/uploads', function () {
        return Inertia::render('Uploads/Index');
    })->name('uploads');
});

// Product routes
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{sku}', [ProductController::class, 'show'])->name('products.show');

// Default welcome route
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
