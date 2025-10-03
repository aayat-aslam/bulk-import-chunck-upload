<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use SplFileObject;
use Illuminate\Support\Facades\Log;

/**
 * Service class for importing product data from CSV files.
 * 
 * This class handles the complete import process including:
 * - Reading and parsing CSV files
 * - Validating required fields
 * - Detecting and handling duplicate SKUs
 * - Batch processing for better performance
 * - Upserting products (insert or update by SKU)
 */

class ProductCsvImporter
{
    /**
     * The required columns that must be present in the CSV.
     *
     * @var array
     */
    protected $required = ['sku', 'name'];
    
    /**
     * The number of rows to process in each database batch.
     *
     * @var int
     */
    protected $batchSize = 1000;

    /**
     * Import products from a CSV file.
     *
     * Processes the CSV file in batches for better memory efficiency and performance.
     * Returns an array with import statistics.
     *
     * @param string $filePath Path to the CSV file
     * @param int $batchSize Number of rows to process in each batch
     * @return array Import summary with counts of processed/imported/updated records
     * 
     * @throws \RuntimeException If the CSV file cannot be read or is invalid
     */
    public function import($filePath, $batchSize = 1000)
    {
        $this->batchSize = $batchSize;
        
        // Initialize summary statistics
        $summary = [
            'total' => 0,      // Total rows processed
            'imported' => 0,   // New products created
            'updated' => 0,    // Existing products updated
            'invalid' => 0,    // Rows with missing required fields
            'duplicates' => 0, // Duplicate SKUs within the same file
        ];
        
        $seenSkus = [];        // Track SKUs to detect duplicates within the file
        $rowsBatch = [];       // Buffer for batch processing
        $summary = ['total'=>0,'imported'=>0,'updated'=>0,'invalid'=>0,'duplicates'=>0];
        $seen = [];
        $rowsBatch = [];

        try {
            $file = new SplFileObject($filePath);
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
            
            $headers = null;
            $lineNumber = 0;
            
            while (!$file->eof()) {
                $lineNumber++;
                $row = $file->fgetcsv();
                
                // Skip empty rows
                if (!$row || (count($row) === 1 && $row[0] === null)) {
                    continue;
                }
                
                // First non-empty row is treated as the header
                if ($headers === null) {
                    $headers = array_map('trim', $row);
                    
                    // Validate headers contain all required columns
                    $missingHeaders = array_diff($this->required, $headers);
                    if (!empty($missingHeaders)) {
                        throw new \RuntimeException(
                            'CSV is missing required headers: ' . implode(', ', $missingHeaders)
                        );
                    }
                    continue;
                }
                
                $summary['total']++;
                
                try {
                    // Combine headers with row values
                    if (count($headers) !== count($row)) {
                        throw new \RuntimeException("Column count does not match header count on line {$lineNumber}");
                    }
                    
                    $assoc = array_combine($headers, $row);
                    
                    // Validate required columns
                    foreach ($this->required as $col) {
                        if (!isset($assoc[$col]) || trim($assoc[$col]) === '') {
                            throw new \RuntimeException("Missing required value for '{$col}' on line {$lineNumber}");
                        }
                    }
                    
                    $sku = trim($assoc['sku']);
                    
                    // Check for duplicate SKUs within the same file
                    if (isset($seenSkus[$sku])) {
                        $summary['duplicates']++;
                        Log::warning("Duplicate SKU found in import: {$sku} on line {$lineNumber}");
                        continue;
                    }
                    $seenSkus[$sku] = true;
                    
                    // Prepare row for database insertion
                    $rowsBatch[] = [
                        'sku' => $sku,
                        'name' => trim($assoc['name']),
                        'description' => isset($assoc['description']) ? trim($assoc['description']) : null,
                        'price' => isset($assoc['price']) && is_numeric($assoc['price']) ? (float)$assoc['price'] : null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    
                    // Process batch if we've reached the batch size
                    if (count($rowsBatch) >= $this->batchSize) {
                        $this->processBatch($rowsBatch, $summary);
                        $rowsBatch = [];
                    }
                    
                } catch (\RuntimeException $e) {
                    $summary['invalid']++;
                    Log::warning("Skipping invalid row at line {$lineNumber}: " . $e->getMessage());
                    continue;
                }
            }

            // Process any remaining rows in the final batch
            if (!empty($rowsBatch)) {
                $this->processBatch($rowsBatch, $summary);
            }
            
            return $summary;
            
        } catch (\Exception $e) {
            Log::error("Failed to process CSV import: " . $e->getMessage());
            throw new \RuntimeException("Failed to process CSV import: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Process a batch of product records.
     * 
     * Determines which records are new vs updates and performs a batch upsert.
     * Updates the summary statistics with the results.
     *
     * @param array $rowsBatch Batch of product data to process
     * @param array &$summary Reference to the summary statistics array
     * @return void
     */
    protected function processBatch(array $rowsBatch, array &$summary)
    {
        if (empty($rowsBatch)) {
            return;
        }
        
        try {
            // Extract SKUs from the batch
            $skus = array_column($rowsBatch, 'sku');
            
            // Check which SKUs already exist in the database
            $existingSkus = Product::whereIn('sku', $skus)
                ->pluck('sku')
                ->toArray();
                
            $existingMap = array_flip($existingSkus);
            
            // Count new vs updated records
            $newCount = 0;
            $updateCount = 0;
            
            foreach ($rowsBatch as $row) {
                isset($existingMap[$row['sku']]) ? $updateCount++ : $newCount++;
            }
            
            // Perform the batch upsert
            // This is atomic and handles both inserts and updates in a single query
            Product::upsert(
                $rowsBatch,
                ['sku'], // Unique identifier
                [        // Columns to update on duplicate key
                    'name',
                    'description',
                    'price',
                    'updated_at'
                ]
            );
            
            // Update summary statistics
            $summary['imported'] += $newCount;
            $summary['updated'] += $updateCount;
            
        } catch (\Exception $e) {
            Log::error("Failed to process batch: " . $e->getMessage());
            throw new \RuntimeException("Failed to process batch: " . $e->getMessage(), 0, $e);
        }
    }
}
