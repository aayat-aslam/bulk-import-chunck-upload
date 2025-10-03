<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * ProductImage Model (Pivot)
 * 
 * Represents the many-to-many relationship between Products and Images.
 * This pivot model includes additional data about the relationship,
 * specifically whether the image is the primary image for the product.
 *
 * @property int $product_id The ID of the related product
 * @property int $image_id The ID of the related image
 * @property bool $is_primary Whether this is the primary image for the product
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * 
 * @property-read \App\Models\Product $product The related product
 * @property-read \App\Models\Image $image The related image
 */

class ProductImage extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_image';
    
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'image_id',
        'is_primary'
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the product that owns the image.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    /**
     * Get the image that belongs to the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function image()
    {
        return $this->belongsTo(Image::class);
    }
    
    /**
     * Scope a query to only include primary images.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
