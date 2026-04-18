<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
// GET /api/customer/addresses
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->addresses()->latest()->get());
    }

    // POST /api/customer/addresses
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label'         => 'nullable|string|max:50',
            'street_line_1' => 'required|string|max:255',
            'street_line_2' => 'nullable|string|max:255',
            'city'          => 'required|string|max:100',
            'state'         => 'required|string|max:100',
            'zip'           => 'required|string|max:20',
            'country'       => 'required|string|max:100',
            'phone'         => 'required|string|max:20',
            'is_default'    => 'boolean',
        ]);

        $user = $request->user();

        // If this is the first address or marked as default, handle existing defaults
        if (empty($user->addresses()->count()) || ($data['is_default'] ?? false)) {
            $user->addresses()->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        $address = $user->addresses()->create($data);

        return response()->json([
            'message' => 'Address added.',
            'address' => $address,
        ], 201);
    }

    // PATCH /api/customer/addresses/{address}
    public function update(Request $request, Address $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);

        $data = $request->validate([
            'label'         => 'nullable|string|max:50',
            'street_line_1' => 'sometimes|string|max:255',
            'street_line_2' => 'nullable|string|max:255',
            'city'          => 'sometimes|string|max:100',
            'state'         => 'sometimes|string|max:100',
            'zip'           => 'sometimes|string|max:20',
            'country'       => 'sometimes|string|max:100',
            'phone'         => 'sometimes|string|max:20',
        ]);

        $address->update($data);

        return response()->json([
            'message' => 'Address updated.',
            'address' => $address->fresh(),
        ]);
    }

    // PATCH /api/customer/addresses/{address}/default
    public function setDefault(Request $request, Address $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);

        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response()->json(['message' => 'Default address updated.']);
    }

    // DELETE /api/customer/addresses/{address}
    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);

        if ($address->is_default) {
            return response()->json([
                'message' => 'Cannot delete your default address. Set another as default first.',
            ], 422);
        }

        $address->delete();

        return response()->json(['message' => 'Address deleted.']);
    }

    // Ensure customer can only touch their own addresses
    private function authorizeAddress(Request $request, Address $address): void
    {
        if ($address->user_id !== $request->user()->id) {
            abort(403, 'Forbidden.');
        }
    }
}
