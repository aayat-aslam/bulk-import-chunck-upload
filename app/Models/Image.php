<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * Image Model
 *
 * Represents an image in the system, which can be associated with products.
 * Each image can have multiple variants (e.g., different sizes) and can be
 * associated with multiple products.
 *
 * @property int $id
 * @property int $upload_id The ID of the associated upload record
 * @property string $variant The image variant (e.g., 'original', '256', '512')
 * @property string $path The storage path to the image file
 * @property string $mime The MIME type of the image
 * @property int $width The width of the image in pixels
 * @property int $height The height of the image in pixels
 * @property string $checksum MD5 checksum of the image file
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Upload $upload The upload this image belongs to
 * @property-read \Illuminate\Database\Eloquent\Collection|Product[] $products Products this image is associated with
 */

class Image extends Model
{
    // Image variants
    public const VARIANT_ORIGINAL = 'original';
    public const VARIANT_THUMBNAIL = '256';
    public const VARIANT_MEDIUM = '512';
    public const VARIANT_LARGE = '1024';

    /**
     * Get all available variants
     *
     * @return array
     */
    public static function getAvailableVariants(): array
    {
        return [
            self::VARIANT_ORIGINAL => 'Original',
            self::VARIANT_THUMBNAIL => 'Thumbnail (256px)',
            self::VARIANT_MEDIUM => 'Medium (512px)',
            self::VARIANT_LARGE => 'Large (1024px)',
        ];
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'upload_id',
        'variant',
        'path',
        'mime',
        'width',
        'height',
        'checksum'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the URL to the image variant.
     *
     * @param string|null $variant The variant to get the URL for (defaults to original)
     * @return string
     */
    public function getUrl(string $variant = null): string
    {
        // Always use the public disk for generating URLs
        if ($variant && $variant !== self::VARIANT_ORIGINAL) {
            $variantImage = $this->upload->images()
                ->where('variant', $variant)
                ->first();

            if ($variantImage) {
                // Remove any existing path prefixes and get just the filename
                $filename = basename($variantImage->path);
                // Return URL in the format /uploads/filename
                return '/uploads/' . $filename;
            }
        }

        // Remove any existing path prefixes and get just the filename
        $filename = basename($this->path);
        // Return URL in the format /uploads/filename_orig
        return '/uploads/' . $filename . ($variant === self::VARIANT_ORIGINAL ? '_orig' : '');
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        // Add any computed attributes here
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array<int, string>
     */
    protected $with = [
        // Add relationships to always eager load
    ];

    /**
     * Get the upload that this image belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
    /**
     * Get all products that this image is associated with.
     *
     * This defines a many-to-many relationship with the Product model through
     * the product_image pivot table. The pivot table includes an 'is_primary' flag
     * to indicate if this is the primary image for the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_image')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}

