<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // GET /api/customer/profile
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('addresses');

        return response()->json($user);
    }

    // PATCH /api/customer/profile
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'email'         => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone'         => 'sometimes|string|max:20',
            'date_of_birth' => 'sometimes|date|before:today',
            'avatar'        => 'sometimes|url|max:500',
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated.',
            'user'    => $user->fresh(),
        ]);
    }

    // PATCH /api/customer/profile/password
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string|current_password',
            'password'         => 'required|string|min:8|confirmed|different:current_password',
        ]);

        $request->user()->update([
            'password' => $request->password,
        ]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    // DELETE /api/customer/profile
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|current_password',
        ]);

        $user = $request->user();

        // Revoke all tokens first
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted.']);
    }
}
