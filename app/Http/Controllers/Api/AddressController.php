<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Get list of addresses for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderBy('is_default', 'desc')->get();
        return response()->json($addresses);
    }

    /**
     * Store a newly created address in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_name' => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:50',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state_province' => 'nullable|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country_code' => 'required|string|size:2',
            'is_default' => 'nullable|boolean',
        ]);

        $user = $request->user();

        // If marked default, unset previous default addresses
        if (!empty($validated['is_default'])) {
            $user->addresses()->update(['is_default' => false]);
        }

        // If this is the user's first address, force it to be default
        if ($user->addresses()->count() === 0) {
            $validated['is_default'] = true;
        }

        $address = $user->addresses()->create($validated);

        return response()->json([
            'message' => 'Address saved successfully.',
            'address' => $address,
        ]);
    }

    /**
     * Remove the specified address from storage.
     *
     * @param Request $request
     * @param mixed $id
     * @return JsonResponse
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail(intval($id));
        $wasDefault = $address->is_default;

        $address->delete();

        // If we deleted the default, set next available to default
        if ($wasDefault) {
            $next = $request->user()->addresses()->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return response()->json(['message' => 'Address deleted successfully.']);
    }
}
