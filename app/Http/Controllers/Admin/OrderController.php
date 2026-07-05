<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Display a listing of all users' orders.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = Order::with(['user', 'shippingMethod', 'items.variant.product']);

        // Filter by order fulfillment status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        // Filter by keyword search (order number, customer name, email)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('shipping_name', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return Inertia::render('admin/orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'payment_status', 'search']),
        ]);
    }
}
