<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService)
    {
    }

    /**
     * Get authenticated user's cart details.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $details = $this->cartService->getCartDetailsForUser($request->user());
        return response()->json($details);
    }

    /**
     * Add item to authenticated user's cart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => 'required|integer|exists:variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $this->cartService->addItemToUserCart(
                $request->user(),
                $validated['variant_id'],
                $validated['quantity']
            );
            return response()->json(['message' => 'Item added to cart successfully.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update cart item quantity.
     *
     * @param Request $request
     * @param mixed $cartItemId
     * @return JsonResponse
     */
    public function update(Request $request, $cartItemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        try {
            $this->cartService->updateItemInUserCart(
                $request->user(),
                intval($cartItemId),
                $validated['quantity']
            );
            return response()->json(['message' => 'Cart updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove single item from cart.
     *
     * @param Request $request
     * @param mixed $cartItemId
     * @return JsonResponse
     */
    public function destroy(Request $request, $cartItemId): JsonResponse
    {
        $this->cartService->removeItemFromUserCart($request->user(), intval($cartItemId));
        return response()->json(['message' => 'Item removed from cart.']);
    }

    /**
     * Clear all items from cart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clear(Request $request): JsonResponse
    {
        $this->cartService->clearUserCart($request->user());
        return response()->json(['message' => 'Cart cleared.']);
    }

    /**
     * Post-query compiled cart details for guest local storage items.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function guestDetails(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'present|array',
            'items.*.variant_id' => 'required|integer|exists:variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $details = $this->cartService->getCartDetailsForGuest($validated['items']);
        return response()->json($details);
    }

    /**
     * Merge guest cart items into authenticated user's cart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function merge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'present|array',
            'items.*.variant_id' => 'required|integer|exists:variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $this->cartService->mergeGuestCart($request->user(), $validated['items']);
        return response()->json(['message' => 'Cart merged successfully.']);
    }
}
