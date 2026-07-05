<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->paragraphs(3, true),
            'summary' => $this->faker->sentence(),
            'status' => 'active',
            'thumbnail' => $this->faker->imageUrl(),
            'images' => [$this->faker->imageUrl(), $this->faker->imageUrl()],
            'options' => [
                ['name' => 'Color', 'values' => ['Black', 'White']],
            ],
        ];
    }
}
