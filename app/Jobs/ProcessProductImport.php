<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use League\Csv\Reader;

class ProcessProductImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;

    /**
     * Create a new job instance.
     *
     * @param string $filePath
     * @return void
     */
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function handle(): void
    {
        try {
            Log::info('Starting CSV import from path: ' . $this->filePath);

            // Check if the file exists
            if (!file_exists($this->filePath)) {
                $error = "CSV file not found at path: " . $this->filePath;
                Log::error($error);
                throw new \Exception($error);
            }

            // Check if file is readable
            if (!is_readable($this->filePath)) {
                $error = "CSV file is not readable: " . $this->filePath;
                Log::error($error);
                throw new \Exception($error);
            }

            // Create CSV reader
            $csv = Reader::createFromPath($this->filePath, 'r');
            $csv->setHeaderOffset(0); // Set the CSV header offset

            $header = $csv->getHeader(); // Get the header row
            Log::info('CSV headers: ' . implode(', ', $header));

            // Verify required columns exist
            $requiredColumns = ['sku', 'name'];
            foreach ($requiredColumns as $column) {
                if (!in_array($column, $header)) {
                    $error = "Missing required column in CSV: " . $column;
                    Log::error($error);
                    throw new \Exception($error);
                }
            }

            $count = 0;
            $errors = [];

            // Process each row
            foreach ($csv->getRecords() as $index => $record) {
                try {
                    // Skip empty rows
                    if (empty(array_filter($record))) {
                        continue;
                    }

                    // Process the product record
                    $this->processProduct($record);
                    $count++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                    Log::error("Error processing row " . ($index + 2) . ": " . $e->getMessage());
                    continue;
                }
            }

            $message = "Successfully processed {$count} records from CSV";
            if (!empty($errors)) {
                $message .= " with " . count($errors) . " errors";
                Log::warning(implode("\n", $errors));
            }
            Log::info($message);

            // Delete the file after processing
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
                Log::info('Temporary CSV file deleted: ' . $this->filePath);
            }

        } catch (\Exception $e) {
            Log::error('Error processing CSV import: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a single product record
     *
     * @param array $record
     * @return void
     */
    /**
     * Clean a string by removing extra whitespace and special characters
     *
     * @param string|null $value
     * @return string|null
     */
    protected function cleanString($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value); // Replace multiple spaces with a single space
        return $value === '' ? null : $value;
    }

    /**
     * Parse a number from a string, handling different decimal separators
     *
     * @param mixed $value
     * @return float
     */
    protected function parseNumber($value)
    {
        if (is_numeric($value)) {
            return (float)$value;
        }

        // Handle different decimal separators
        $value = str_replace([',', ' '], ['.', ''], $value);

        return is_numeric($value) ? (float)$value : 0;
    }

    /**
     * Process a single product record
     *
     * @param array $record
     * @return void
     * @throws \Exception
     */
    protected function processProduct(array $record) :void
    {
        try {
            // Log the raw record for debugging
            Log::debug('Processing product record', ['record' => $record]);

            // Clean and validate the record data
            $record = array_map('trim', $record);
            $record = array_filter($record);

            // Map CSV columns to database fields
            $productData = [
                'sku' => $this->cleanString($record['sku'] ?? null),
                'name' => $this->cleanString($record['name'] ?? null),
                'description' => $this->cleanString($record['description'] ?? null),
                'price' => $this->parseNumber($record['price'] ?? 0),
                // Set primary_image_id to null if not provided to avoid foreign key constraint issues
                'primary_image_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Skip if required fields are missing
            if (empty($productData['sku']) || empty($productData['name'])) {
                Log::warning('Skipping product import - missing required fields', [
                    'sku' => $productData['sku'],
                    'name' => $productData['name']
                ]);
                return;
            }

            // Log before update or create
            Log::info('Attempting to upsert product', [
                'sku' => $productData['sku'],
                'data' => $productData
            ]);

            try {
                // Update or create the product
                $product = \App\Models\Product::query()->updateOrCreate(
                    ['sku' => $productData['sku']],
                    $productData
                );

                Log::info('Successfully processed product', [
                    'id' => $product->id,
                    'sku' => $product->sku
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to upsert product', [
                    'sku' => $productData['sku'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error in processProduct', [
                'record' => $record,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
