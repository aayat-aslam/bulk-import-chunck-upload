<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

/**
 * A command to generate mock product data for testing and development purposes.
 *
 * This command creates:
 * - A CSV file with mock product data (SKU, name, price)
 * - Randomly generated product images
 *
 * The generated data can be used for testing the product import/export functionality,
 * populating development environments, or load testing the application.
 */

class GenerateMockProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     *
     * Usage:
     * php artisan generate:mock-products
     * php artisan generate:mock-products 5000 100  // Generate 5000 rows and 100 images
     *
     * @param int $rows Number of product rows to generate (default: 10000)
     * @param int $images Number of product images to generate (default: 300)
     */
    protected $signature = 'generate:mock-products {rows=100} {images=30}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate mock product data including CSV and images for testing and development';

    /**
     * Directory where mock images will be stored.
     *
     * @var string
     */
    protected string $imagesDirectory = 'mock_images';

    /**
     * Filename for the generated CSV file.
     *
     * @var string
     */
    protected string $csvFilename = 'mock_products.csv';

    /**
     * Execute the console command.
     *
     * This is the main entry point for the command. It:
     * 1. Parses the command line arguments
     * 2. Generates the CSV file with mock product data
     * 3. Generates the specified number of product images
     * 4. Outputs success/error messages
     *
     * @return int Returns 0 on success, 1 on failure
     */
    public function handle()
    {
        $rows   = (int) $this->argument('rows');
        $images = (int) $this->argument('images');

        // 1️⃣ Generate CSV
        $this->generateCsv($rows);

        // 2️⃣ Generate Images
        $this->generateImages($images);

        $this->info("✅ Mock products generated successfully!");
        return 0;
    }

    /**
     * Generate a CSV file with mock product data for testing and development.
     *
     * This method creates a CSV file with the following columns:
     * - sku: Auto-generated in the format 'SKU000001', 'SKU000002', etc.
     * - name: Simple product name with sequential numbering
     * - price: Random price between 1.00 and 100.00
     *
     * The file is saved to the storage/app directory with the filename specified
     * in the $csvFilename property.
     *
     * @param int $rows Number of product rows to generate (default: 10,000)
     * @return void
     * @throws \RuntimeException If unable to create or write to the CSV file
     */
    protected function generateCsv($rows)
    {
        $filePath = storage_path("app/{$this->csvFilename}");
        $fp = fopen($filePath, 'w');

        if (!$fp) {
            $this->error("Failed to create CSV file at: {$filePath}");
            return;
        }

        // Header
        fputcsv($fp, ['sku', 'name', 'description', 'price']);

        // Rows
        for ($i = 1; $i <= $rows; $i++) {
            $sku   = 'SKU' . str_pad($i, 6, '0', STR_PAD_LEFT);
            $name  = "Product $i";
            $description = "Description is manual for testing of Product $i with it's SKU is $sku and Name is $name";
            $price = mt_rand(100, 10000) / 100; // random price
            fputcsv($fp, [$sku, $name, $description, $price]);
        }

        fclose($fp);

        $this->info("📄 CSV generated at: {$filePath}");
    }

    /**
     * Generate random product images with unique identifiers and dimensions.
     *
     * This method creates square images with the following characteristics:
     * - Random size between 200x200 and 800x800 pixels
     * - Random background color
     * - Image number displayed in the center
     * - Saved as JPEG in the directory specified by $imagesDirectory
     *
     * The images are named sequentially as 'image_1.jpg', 'image_2.jpg', etc.
     * Any existing images in the target directory will be deleted first.
     *
     * @param int $count Number of images to generate (default: 300)
     * @return void
     * @throws \RuntimeException If the images directory cannot be created
     */
    protected function generateImages($count)
    {
        $dir = storage_path("app/{$this->imagesDirectory}");

        // Create directory if it doesn't exist
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            $this->error("Failed to create directory: {$dir}");
            return;
        }

        // Clear existing images in the directory
        $this->clearExistingImages($dir);

        // ✅ Correct for Intervention v3
        $manager = new \Intervention\Image\ImageManager(
            new \Intervention\Image\Drivers\Gd\Driver()
        );
        $watermarkPath = storage_path('app/watermarks/img.png');

        for ($i = 1; $i <= $count; $i++) {
            $width  = mt_rand(200, 800);
            $height = mt_rand(200, 800);

            $color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));

            // ✅ v3 uses create()
            $image = $manager->create($width, $height, $color);

            // Draw text in the bottom
            $image->text("Image $i", $width / 2, $height / 2, function ($font) {
                $font->size(30);
                $font->color('#04F0F4');
                $font->align('bottom');
                $font->valign('bottom');
            });

            // Load and prepare watermark
            $watermark = $manager->read($watermarkPath);

            // Resize watermark to fit diagonally without overwhelming the image
            $maxWatermarkWidth = $width * 0.4;
            $watermark->scaleDown(width: $maxWatermarkWidth);

            // Rotate watermark (diagonal)
            $watermark->rotate(45); // Negative for bottom-left to top-right

            // Optional: reduce opacity (if using GD, this won't work with JPEGs unless watermark has transparency)
            // $watermark->opacity(50); // Comment out if not needed

            // Place watermark in center with diagonal orientation
            $image->place($watermark, 'center');

            // Save to file
            $image->save("{$dir}/image_{$i}.jpg");
        }

        $this->info("🖼️ {$count} images generated in: {$dir}");
    }

    /**
     * Clear existing images in the specified directory.
     *
     * This helper method removes all image files (*.jpg, *.jpeg, *.png, *.gif)
     * from the given directory before generating new ones to prevent accumulation
     * of old test images.
     *
     * @param string $directory Full path to the directory containing images to clear
     * @return void
     * @throws \RuntimeException If the directory is not writable
     */
    protected function clearExistingImages($directory)
    {
        $files = glob("{$directory}/*.{jpg,jpeg,png,gif}", GLOB_BRACE);

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
