<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\Variant
 *
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property string $sku
 * @property string|null $barcode
 * @property float $price
 * @property float|null $compare_at_price
 * @property float|null $cost_price
 * @property int $inventory_quantity
 * @property bool $track_inventory
 * @property bool $continue_selling_out_of_stock
 * @property float|null $weight
 * @property string $weight_unit
 * @property float|null $width
 * @property float|null $height
 * @property float|null $depth
 * @property string $dimension_unit
 * @property array|null $options
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 */
class Variant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'price',
        'compare_at_price',
        'cost_price',
        'inventory_quantity',
        'track_inventory',
        'continue_selling_out_of_stock',
        'weight',
        'weight_unit',
        'width',
        'height',
        'depth',
        'dimension_unit',
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
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'inventory_quantity' => 'integer',
            'track_inventory' => 'boolean',
            'continue_selling_out_of_stock' => 'boolean',
            'weight' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'depth' => 'decimal:2',
            'options' => 'array',
        ];
    }

    /**
     * Get the product that owns the variant.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
