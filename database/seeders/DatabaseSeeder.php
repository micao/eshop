<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => \App\Models\User::ROLE_ADMIN,
        ]);

        $this->call(ProductSeeder::class);

        $shippingMethod = \App\Models\ShippingMethod::create([
            'name' => 'bpost Standard Shipping',
            'carrier' => 'bpost',
            'gateway_driver' => 'flat_rate',
            'base_price' => 4.95,
            'is_active' => true,
        ]);

        \App\Models\ShippingMethod::create([
            'name' => 'GLS European Express',
            'carrier' => 'gls',
            'gateway_driver' => 'flat_rate',
            'base_price' => 9.95,
            'is_active' => true,
        ]);

        // Create standard customer user
        $customer = User::factory()->create([
            'name' => 'Customer User',
            'email' => 'customer@example.com',
            'role' => \App\Models\User::ROLE_USER,
        ]);

        // Seed addresses
        \App\Models\UserAddress::create([
            'user_id' => 1,
            'recipient_name' => 'Test User Shipping',
            'recipient_phone' => '+32 499 12 34 56',
            'address_line_1' => 'Rue Royale 180',
            'address_line_2' => 'Appt 4B',
            'city' => 'Brussels',
            'state_province' => 'Brussels-Capital Region',
            'postal_code' => '1000',
            'country_code' => 'BE',
            'is_default' => true,
        ]);

        \App\Models\UserAddress::create([
            'user_id' => 1,
            'recipient_name' => 'Test User Billing',
            'recipient_phone' => '+32 499 12 34 56',
            'address_line_1' => 'Avenue Louise 250',
            'city' => 'Brussels',
            'state_province' => 'Brussels-Capital Region',
            'postal_code' => '1050',
            'country_code' => 'BE',
            'is_default' => false,
        ]);

        \App\Models\UserAddress::create([
            'user_id' => $customer->id,
            'recipient_name' => 'Customer User',
            'recipient_phone' => '+49 170 1234567',
            'address_line_1' => 'Friedrichstraße 95',
            'city' => 'Berlin',
            'postal_code' => '10117',
            'country_code' => 'DE',
            'is_default' => true,
        ]);

        // Seed carts and cart items
        $cart1 = \App\Models\Cart::create(['user_id' => 1]);
        $cart2 = \App\Models\Cart::create(['user_id' => $customer->id]);

        $v1 = \App\Models\Variant::where('sku', 'IPH15-BLK-128')->first();
        $v2 = \App\Models\Variant::where('sku', 'SONY-XM5-BLK')->first();
        $v3 = \App\Models\Variant::where('sku', 'APL-W9-45')->first();

        if ($v1) {
            \App\Models\CartItem::create([
                'cart_id' => $cart1->id,
                'variant_id' => $v1->id,
                'quantity' => 1,
            ]);
        }
        if ($v2) {
            \App\Models\CartItem::create([
                'cart_id' => $cart1->id,
                'variant_id' => $v2->id,
                'quantity' => 2,
            ]);
        }
        if ($v3) {
            \App\Models\CartItem::create([
                'cart_id' => $cart2->id,
                'variant_id' => $v3->id,
                'quantity' => 1,
            ]);
        }

        // Seed orders and order items
        $order1 = \App\Models\Order::create([
            'user_id' => 1,
            'shipping_method_id' => $shippingMethod->id,
            'payment_method' => 'stripe',
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_intent_id' => 'pi_mock_1234567890',
            'order_number' => 'ORD-20260704-0001',
            'subtotal' => 1495.00,
            'shipping_cost' => 4.95,
            'tax' => 0.00,
            'grand_total' => 1499.95,
            'shipping_name' => 'Test User Shipping',
            'shipping_phone' => '+32 499 12 34 56',
            'shipping_address_line_1' => 'Rue Royale 180',
            'shipping_address_line_2' => 'Appt 4B',
            'shipping_city' => 'Brussels',
            'shipping_state_province' => 'Brussels-Capital Region',
            'shipping_postal_code' => '1000',
            'shipping_country_code' => 'BE',
        ]);

        $vAeron = \App\Models\Variant::where('sku', 'HM-AERON-SIZEB')->first();
        if ($vAeron) {
            \App\Models\OrderItem::create([
                'order_id' => $order1->id,
                'variant_id' => $vAeron->id,
                'quantity' => 1,
                'price' => 1495.00,
                'sku' => $vAeron->sku,
            ]);
        }

        $order2 = \App\Models\Order::create([
            'user_id' => $customer->id,
            'shipping_method_id' => $shippingMethod->id,
            'payment_method' => 'stripe',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_intent_id' => 'pi_mock_9876543210',
            'order_number' => 'ORD-20260704-0002',
            'subtotal' => 1099.00,
            'shipping_cost' => 0.00,
            'tax' => 0.00,
            'grand_total' => 1099.00,
            'shipping_name' => 'Customer User',
            'shipping_phone' => '+49 170 1234567',
            'shipping_address_line_1' => 'Friedrichstraße 95',
            'shipping_city' => 'Berlin',
            'shipping_postal_code' => '10117',
            'shipping_country_code' => 'DE',
        ]);

        $vMba = \App\Models\Variant::where('sku', 'MBA-M3-8G-512')->first();
        if ($vMba) {
            \App\Models\OrderItem::create([
                'order_id' => $order2->id,
                'variant_id' => $vMba->id,
                'quantity' => 1,
                'price' => 1099.00,
                'sku' => $vMba->sku,
            ]);
        }
    }
}
