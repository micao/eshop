<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Laravel\Scout\Searchable;

/**
 * App\Models\Product
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $summary
 * @property string $status
 * @property string|null $thumbnail
 * @property array|null $images
 * @property array|null $options
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Category|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Variant[] $variants
 */
class Product extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'category_id',
        'brand_id',
        'supplier_id',
        'name',
        'slug',
        'description',
        'summary',
        'status',
        'thumbnail',
        'images',
        'options',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'images' => 'array',
            'options' => 'array',
        ];
    }

    /**
     * Get the category that owns the product.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the variants for the product.
     *
     * @return HasMany<Variant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    /**
     * Get the brand that owns the product.
     *
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the supplier that owns the product.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['category', 'variants', 'brand', 'supplier']);

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'status' => $this->status,
            'category' => $this->category ? [
                'id' => (int) $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,
            'brand' => $this->brand ? [
                'id' => (int) $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ] : null,
            'supplier' => $this->supplier ? [
                'id' => (int) $this->supplier->id,
                'name' => $this->supplier->name,
                'slug' => $this->supplier->slug,
            ] : null,
            'variants' => $this->variants->map(fn ($variant) => [
                'id' => (int) $variant->id,
                'sku' => $variant->sku,
                'price' => (float) $variant->price,
                'inventory_quantity' => (int) $variant->inventory_quantity,
            ])->toArray(),
        ];
    }
}
