<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;

class GraphQLController extends Controller
{
    /**
     * Handle incoming GraphQL POST requests.
     *
     * @return JsonResponse
     */
    public function handle(Request $request)
    {
        try {
            $input = $request->json()->all();
            $query = $input['query'] ?? null;
            $variables = $input['variables'] ?? null;

            if (! $query) {
                return response()->json([
                    'errors' => [['message' => 'GraphQL query is required.']],
                ], 400);
            }

            // Define GraphQL Types
            $productType = new ObjectType([
                'name' => 'Product',
                'fields' => [
                    'id' => Type::nonNull(Type::id()),
                    'name' => Type::nonNull(Type::string()),
                    'slug' => Type::nonNull(Type::string()),
                    'summary' => Type::string(),
                    'thumbnail' => Type::string(),
                ],
            ]);

            $productVariantType = new ObjectType([
                'name' => 'ProductVariant',
                'fields' => [
                    'id' => Type::nonNull(Type::id()),
                    'name' => Type::nonNull(Type::string()),
                    'sku' => Type::nonNull(Type::string()),
                    'price' => Type::nonNull(Type::float()),
                    'product' => [
                        'type' => Type::nonNull($productType),
                        'resolve' => function ($variant) {
                            return $variant->product;
                        },
                    ],
                ],
            ]);

            $cartItemType = new ObjectType([
                'name' => 'CartItem',
                'fields' => [
                    'id' => Type::nonNull(Type::id()),
                    'quantity' => Type::nonNull(Type::int()),
                    'variant' => [
                        'type' => Type::nonNull($productVariantType),
                        'resolve' => function ($item) {
                            return $item->variant;
                        },
                    ],
                ],
            ]);

            $cartType = new ObjectType([
                'name' => 'Cart',
                'fields' => [
                    'id' => Type::nonNull(Type::id()),
                    'totalPrice' => [
                        'type' => Type::nonNull(Type::float()),
                        'resolve' => function ($cart) {
                            return (float) $cart->items->sum(function ($item) {
                                return ($item->variant->price ?? 0) * $item->quantity;
                            });
                        },
                    ],
                    'items' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull($cartItemType))),
                        'resolve' => function ($cart) {
                            return $cart->items;
                        },
                    ],
                ],
            ]);

            $orderItemType = new ObjectType([
                'name' => 'OrderItem',
                'fields' => [
                    'id' => Type::nonNull(Type::id()),
                    'quantity' => Type::nonNull(Type::int()),
                    'price' => Type::nonNull(Type::float()),
                    'sku' => Type::string(),
                    'variant' => [
                        'type' => $productVariantType,
                        'resolve' => function ($item) {
                            return $item->variant;
                        },
                    ],
                ],
            ]);

            $orderType = new ObjectType([
                'name' => 'Order',
                'fields' => [
                    'id' => Type::nonNull(Type::id()),
                    'orderNumber' => [
                        'type' => Type::nonNull(Type::string()),
                        'resolve' => function ($order) {
                            return $order->order_number;
                        },
                    ],
                    'status' => Type::nonNull(Type::string()),
                    'paymentStatus' => [
                        'type' => Type::nonNull(Type::string()),
                        'resolve' => function ($order) {
                            return $order->payment_status;
                        },
                    ],
                    'totalAmount' => [
                        'type' => Type::nonNull(Type::float()),
                        'resolve' => function ($order) {
                            return (float) $order->grand_total;
                        },
                    ],
                    'createdAt' => [
                        'type' => Type::nonNull(Type::string()),
                        'resolve' => function ($order) {
                            return $order->created_at->toIso8601String();
                        },
                    ],
                    'items' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull($orderItemType))),
                        'resolve' => function ($order) {
                            return $order->items;
                        },
                    ],
                ],
            ]);

            $userType = new ObjectType([
                'name' => 'User',
                'fields' => [
                    'id' => Type::nonNull(Type::id()),
                    'name' => Type::nonNull(Type::string()),
                    'email' => Type::nonNull(Type::string()),
                    'cart' => [
                        'type' => $cartType,
                        'resolve' => function ($user) {
                            return $user->cart;
                        },
                    ],
                    'orders' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull($orderType))),
                        'resolve' => function ($user) {
                            return $user->orders;
                        },
                    ],
                ],
            ]);

            // Query root
            $queryType = new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'usersCarts' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull($userType))),
                        'resolve' => function () {
                            $currentUser = auth()->user();
                            if ($currentUser && $currentUser->isAdmin()) {
                                return User::with(['cart.items.variant.product'])->get();
                            }
                            if ($currentUser) {
                                return User::where('id', $currentUser->id)->with(['cart.items.variant.product'])->get();
                            }

                            return [];
                        },
                    ],
                    'usersOrders' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull($userType))),
                        'resolve' => function () {
                            $currentUser = auth()->user();
                            if ($currentUser && $currentUser->isAdmin()) {
                                return User::with(['orders.items.variant.product'])->get();
                            }
                            if ($currentUser) {
                                return User::where('id', $currentUser->id)->with(['orders.items.variant.product'])->get();
                            }

                            return [];
                        },
                    ],
                ],
            ]);

            $schema = new Schema([
                'query' => $queryType,
            ]);

            $result = GraphQL::executeQuery($schema, $query, null, null, $variables);
            $output = $result->toArray();

            return response()->json($output);

        } catch (\Exception $e) {
            Log::error('GraphQL Error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'errors' => [['message' => $e->getMessage()]],
            ], 500);
        }
    }
}
