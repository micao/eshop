<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Variant>
 */
class VariantFactory extends Factory
{
    protected $model = Variant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => $this->faker->words(3, true),
            'sku' => strtoupper($this->faker->unique()->bothify('???-#####')),
            'barcode' => $this->faker->unique()->ean13(),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'compare_at_price' => $this->faker->randomFloat(2, 20, 2000),
            'cost_price' => $this->faker->randomFloat(2, 5, 500),
            'inventory_quantity' => $this->faker->numberBetween(0, 100),
            'track_inventory' => true,
            'continue_selling_out_of_stock' => false,
            'weight' => $this->faker->randomFloat(2, 50, 5000),
            'weight_unit' => 'g',
            'width' => $this->faker->randomFloat(2, 1, 100),
            'height' => $this->faker->randomFloat(2, 1, 100),
            'depth' => $this->faker->randomFloat(2, 1, 100),
            'dimension_unit' => 'cm',
            'options' => ['Color' => 'Black'],
        ];
    }
}
