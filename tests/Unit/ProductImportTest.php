<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Product;
use App\Services\ProductCsvImporter;

/**
 * Test suite for product import functionality.
 * 
 * This test class verifies the behavior of the ProductCsvImporter service,
 * ensuring it correctly handles the creation and updating of products from CSV data.
 * It tests both the happy path and edge cases for the import process.
 */

class ProductImportTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * The path where test CSV files will be stored.
     *
     * @var string
     */
    protected $testCsvPath;
    
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testCsvPath = storage_path('app/testing/products.csv');
        
        // Ensure the test directory exists
        if (!is_dir(dirname($this->testCsvPath))) {
            mkdir(dirname($this->testCsvPath), 0777, true);
        }
    }
    
    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->testCsvPath)) {
            unlink($this->testCsvPath);
        }
        
        parent::tearDown();
    }

    /**
     * Test that the import process correctly handles both creating new products
     * and updating existing ones based on SKU.
     * 
     * This test verifies:
     * - Existing products are updated with new data
     * - New products are created
     * - The import summary contains correct counts
     * 
     * @return void
     */
    public function test_upsert_creates_and_updates_products()
    {
        // Arrange: Create an existing product that will be updated
        Product::create([
            'sku'   => 'SKU1',
            'name'  => 'Old',
            'price' => 10.00
        ]);

        // Test CSV data with one existing SKU (to update) and one new SKU (to create)
        $csvData = <<<CSV
sku,name,price
SKU1,NewName,11.00
SKU2,Product2,9.50
CSV;

        // Act: Write test CSV file and run the import
        file_put_contents($this->testCsvPath, $csvData);
        $importer = new ProductCsvImporter();
        $summary = $importer->import($this->testCsvPath, 2);

        // Assert: Verify database state after import
        
        // Check that existing product was updated correctly
        $this->assertDatabaseHas('products', [
            'sku'   => 'SKU1',
            'name'  => 'NewName',
            'price' => 11.00,
        ]);

        // Check that new product was created correctly
        $this->assertDatabaseHas('products', [
            'sku'   => 'SKU2',
            'name'  => 'Product2',
            'price' => 9.50,
        ]);

        // Verify the import summary statistics
        $this->assertEquals(2, $summary['total'], 'Total processed count should be 2');
        $this->assertEquals(1, $summary['imported'], 'Should have imported 1 new product');
        $this->assertEquals(1, $summary['updated'], 'Should have updated 1 existing product');
    }
}
