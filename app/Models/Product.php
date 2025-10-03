<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Product Model
 * 
 * Represents a product in the e-commerce system.
 * 
 * @property int $id
 * @property string $sku Unique stock keeping unit identifier
 * @property string $name Product name
 * @property string|null $description Detailed product description
 * @property float|null $price Product price
 * @property int|null $primary_image_id ID of the primary product image
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * 
 * @property-read \Illuminate\Database\Eloquent\Collection|Image[] $images All associated images
 * @property-read Image|null $primaryImage The primary product image
 */

class Product extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sku',
        'name',
        'description',
        'price',
        'primary_image_id'
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        // Add any computed attributes here
    ];

    /**
     * Get all images associated with the product.
     * 
     * This defines a many-to-many relationship with the Image model through
     * the product_image pivot table. The pivot table includes an 'is_primary' flag.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 'product_image')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
    /**
     * Get the primary image of the product.
     * 
     * This defines a one-to-one relationship with the Image model
     * for the primary product image.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function primaryImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'primary_image_id');
    }
}

