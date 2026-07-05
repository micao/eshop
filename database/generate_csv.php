<?php

require __DIR__.'/../vendor/autoload.php';

use Faker\Factory;
use Illuminate\Support\Str;

$faker = Factory::create();

$directory = __DIR__.'/imports';
if (! is_dir($directory)) {
    mkdir($directory, 0755, true);
}

$headers = [
    'product_category',
    'product_name', 'product_slug', 'product_description', 'product_summary', 'product_status', 'product_thumbnail', 'product_images', 'product_options',
    'variant_name', 'variant_sku', 'variant_barcode', 'variant_price', 'variant_compare_at_price', 'variant_cost_price', 'variant_inventory_quantity', 'variant_track_inventory', 'variant_continue_selling_out_of_stock', 'variant_weight', 'variant_weight_unit', 'variant_width', 'variant_height', 'variant_depth', 'variant_dimension_unit', 'variant_options',
];

$categories = ['Electronics', 'Apparel', 'Home', 'Sporting', 'Garden', 'Health', 'Beauty', 'Automotive', 'Toys', 'Office'];

// Clean up old files
foreach (glob($directory.'/products_*.csv') as $oldFile) {
    unlink($oldFile);
}
foreach (glob($directory.'/*_product_*.csv') as $oldFile) {
    unlink($oldFile);
}

$datePrefix = date('Y_m_d_').time();

for ($fileIndex = 1; $fileIndex <= 10; $fileIndex++) {
    $filePath = $directory."/{$datePrefix}_product_{$fileIndex}.csv";
    $file = fopen($filePath, 'w');

    // Write headers
    fputcsv($file, $headers);

    for ($rowIndex = 1; $rowIndex <= 20; $rowIndex++) {
        $category = $categories[$fileIndex - 1];
        $prodNum = (($fileIndex - 1) * 20) + $rowIndex;

        $prodName = $faker->unique()->words(3, true)." ($category)";
        $prodName = ucwords($prodName);
        $slug = Str::slug($prodName);
        $description = $faker->paragraph(4);
        $summary = $faker->sentence();
        $status = 'active';
        $thumbnail = 'https://picsum.photos/300/300?random='.$prodNum;
        $images = json_encode([
            'https://picsum.photos/600/600?random='.($prodNum * 10),
            'https://picsum.photos/600/600?random='.($prodNum * 10 + 1),
        ]);

        // Option schemas
        $options = json_encode([
            ['name' => 'Color', 'values' => ['Black', 'White']],
            ['name' => 'Size', 'values' => ['S', 'M', 'L']],
        ]);

        // Variant data
        $vColor = $faker->randomElement(['Black', 'White']);
        $vSize = $faker->randomElement(['S', 'M', 'L']);
        $variantName = "$prodName - $vColor / $vSize";
        $sku = strtoupper(Str::random(3)).'-'.str_pad($prodNum, 5, '0', STR_PAD_LEFT);
        $barcode = $faker->unique()->ean13();

        $cost = $faker->randomFloat(2, 5, 200);
        $price = $cost * $faker->randomFloat(2, 1.2, 2.5);
        $compareAt = $faker->boolean(30) ? $price * 1.2 : null;

        $qty = $faker->numberBetween(0, 150);
        $trackInv = true;
        $contSelling = $faker->boolean(10);

        $weight = $faker->randomFloat(2, 100, 5000);
        $weightUnit = 'g';
        $width = $faker->randomFloat(2, 5, 50);
        $height = $faker->randomFloat(2, 5, 50);
        $depth = $faker->randomFloat(2, 5, 50);
        $dimUnit = 'cm';

        $vOptions = json_encode([
            'Color' => $vColor,
            'Size' => $vSize,
        ]);

        $row = [
            $category,
            $prodName, $slug, $description, $summary, $status, $thumbnail, $images, $options,
            $variantName, $sku, $barcode, round($price, 2), $compareAt ? round($compareAt, 2) : '', round($cost, 2), $qty, $trackInv ? 1 : 0, $contSelling ? 1 : 0, round($weight, 2), $weightUnit, round($width, 2), round($height, 2), round($depth, 2), $dimUnit, $vOptions,
        ];

        fputcsv($file, $row);
    }

    fclose($file);
}

echo "Successfully generated 10 CSV files in $directory\n";
