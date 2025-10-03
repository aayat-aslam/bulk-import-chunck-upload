<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Upload Model
 *
 * Represents a file upload in the system. This model tracks the upload process,
 * stores metadata about the uploaded file, and maintains the relationship
 * with any processed image variants.
 *
 * @property int $id
 * @property string $upload_id Unique identifier for the upload (UUID)
 * @property string $original_filename The original filename of the uploaded file
 * @property int|null $file_size Size of the uploaded file in bytes
 * @property string|null $file_checksum MD5 checksum of the uploaded file
 * @property string $status Current status of the upload (e.g., 'uploading', 'processing', 'complete', 'failed')
 * @property array|null $meta Additional metadata about the upload (stored as JSON)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|Image[] $images Processed image variants
 */

class Upload extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'upload_id',
        'original_filename',
        'file_size',
        'file_checksum',
        'status',
        'meta'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta' => 'array',
        'file_size' => 'integer',
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
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'uploading',
        'meta' => '[]',
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
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        // 'saved' => UploadSaved::class,
        // 'deleting' => UploadDeleting::class,
    ];

    /**
     * Get all images associated with this upload.
     *
     * An upload can have multiple image variants (e.g., different sizes).
     * This relationship returns all processed image variants for this upload.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    /**
     * Scope a query to only include completed uploads.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'complete');
    }

    /**
     * Scope a query to only include failed uploads.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get the URL to the original uploaded file.
     *
     * @return string|null
     */
    public function getOriginalFileUrlAttribute()
    {
        if ($this->status !== 'complete') {
            return null;
        }

        // Assuming files are stored in the 'uploads' disk
        return $this->original_filename ? \Storage::disk('uploads')->url($this->original_filename) : null;
    }
}
