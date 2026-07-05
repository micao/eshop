<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * Compile cart details for an authenticated user.
     */
    public function getCartDetailsForUser(User $user): array
    {
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $cart->load(['items.variant.product']);

        $formattedItems = [];
        $subtotal = 0;

        foreach ($cart->items as $item) {
            $variant = $item->variant;
            if (! $variant) {
                continue;
            }

            $product = $variant->product;
            $price = floatval($variant->price);
            $itemSubtotal = $price * $item->quantity;
            $subtotal += $itemSubtotal;

            $formattedItems[] = [
                'cart_item_id' => $item->id,
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $product ? "{$product->name} - ".$this->formatOptions($variant->options) : $variant->name,
                'price' => $price,
                'quantity' => $item->quantity,
                'available_stock' => $variant->inventory_quantity,
                'subtotal' => $itemSubtotal,
                'product' => $product ? [
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'thumbnail' => $product->thumbnail,
                ] : null,
            ];
        }

        return [
            'cart_id' => $cart->id,
            'items' => $formattedItems,
            'summary' => [
                'item_count' => count($formattedItems),
                'subtotal' => $subtotal,
                'discount' => 0.00,
                'grand_total' => $subtotal,
            ],
        ];
    }

    /**
     * Compile cart details for a guest user using a list of variant quantities.
     *
     * @param  array  $guestItems  List of ['variant_id' => X, 'quantity' => Y]
     */
    public function getCartDetailsForGuest(array $guestItems): array
    {
        $formattedItems = [];
        $subtotal = 0;

        if (empty($guestItems)) {
            return [
                'cart_id' => null,
                'items' => [],
                'summary' => [
                    'item_count' => 0,
                    'subtotal' => 0.00,
                    'discount' => 0.00,
                    'grand_total' => 0.00,
                ],
            ];
        }

        // Map quantities by variant_id
        $quantities = [];
        foreach ($guestItems as $item) {
            if (isset($item['variant_id']) && isset($item['quantity'])) {
                $quantities[intval($item['variant_id'])] = intval($item['quantity']);
            }
        }

        $variants = Variant::with('product')
            ->whereIn('id', array_keys($quantities))
            ->get();

        foreach ($variants as $variant) {
            $qty = $quantities[$variant->id] ?? 0;
            if ($qty <= 0) {
                continue;
            }

            $product = $variant->product;
            $price = floatval($variant->price);
            $itemSubtotal = $price * $qty;
            $subtotal += $itemSubtotal;

            $formattedItems[] = [
                'cart_item_id' => null, // Guest items don't have DB cart_item record ids
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $product ? "{$product->name} - ".$this->formatOptions($variant->options) : $variant->name,
                'price' => $price,
                'quantity' => $qty,
                'available_stock' => $variant->inventory_quantity,
                'subtotal' => $itemSubtotal,
                'product' => $product ? [
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'thumbnail' => $product->thumbnail,
                ] : null,
            ];
        }

        return [
            'cart_id' => null,
            'items' => $formattedItems,
            'summary' => [
                'item_count' => count($formattedItems),
                'subtotal' => $subtotal,
                'discount' => 0.00,
                'grand_total' => $subtotal,
            ],
        ];
    }

    /**
     * Add a variant item to the user's cart, verifying stock availability.
     *
     * @throws \Exception
     */
    public function addItemToUserCart(User $user, int $variantId, int $quantity): void
    {
        $variant = Variant::findOrFail($variantId);
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $existingItem = $cart->items()->where('variant_id', $variantId)->first();
        $targetQuantity = ($existingItem ? $existingItem->quantity : 0) + $quantity;

        // Perform stock availability validation
        if ($variant->track_inventory && ! $variant->continue_selling_out_of_stock) {
            if ($targetQuantity > $variant->inventory_quantity) {
                throw new \Exception("Cannot add requested quantity. Only {$variant->inventory_quantity} units available.");
            }
        }

        if ($existingItem) {
            $existingItem->update(['quantity' => $targetQuantity]);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'variant_id' => $variantId,
                'quantity' => $targetQuantity,
            ]);
        }
    }

    /**
     * Update the quantity of a specific item in the user's cart.
     *
     * @throws \Exception
     */
    public function updateItemInUserCart(User $user, int $cartItemId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeItemFromUserCart($user, $cartItemId);

            return;
        }

        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        $item = $cart->items()->findOrFail($cartItemId);
        $variant = $item->variant;

        if ($variant && $variant->track_inventory && ! $variant->continue_selling_out_of_stock) {
            if ($quantity > $variant->inventory_quantity) {
                throw new \Exception("Cannot update quantity. Only {$variant->inventory_quantity} units available.");
            }
        }

        $item->update(['quantity' => $quantity]);
    }

    /**
     * Remove a specific item from the user's cart.
     */
    public function removeItemFromUserCart(User $user, int $cartItemId): void
    {
        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        $item = $cart->items()->findOrFail($cartItemId);
        $item->delete();
    }

    /**
     * Clear all items from the user's cart.
     */
    public function clearUserCart(User $user): void
    {
        $cart = Cart::where('user_id', $user->id)->first();
        if ($cart) {
            $cart->items()->delete();
        }
    }

    /**
     * Merge guest cart items into the authenticated user's cart.
     */
    public function mergeGuestCart(User $user, array $guestItems): void
    {
        if (empty($guestItems)) {
            return;
        }

        DB::transaction(function () use ($user, $guestItems) {
            foreach ($guestItems as $item) {
                if (empty($item['variant_id']) || empty($item['quantity'])) {
                    continue;
                }

                try {
                    $this->addItemToUserCart($user, intval($item['variant_id']), intval($item['quantity']));
                } catch (\Exception $e) {
                    // Silently fail or merge what is possible for a clean checkout flow
                    continue;
                }
            }
        });
    }

    /**
     * Helper to format variant options.
     */
    private function formatOptions(?array $options): string
    {
        if (empty($options)) {
            return 'Default';
        }

        return implode(' / ', array_values($options));
    }
}
