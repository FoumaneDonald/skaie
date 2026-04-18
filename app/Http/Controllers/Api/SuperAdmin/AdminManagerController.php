<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminManagerController extends Controller
{
 // GET /api/super-admin/admins
    public function index(): JsonResponse
    {
        $admins = User::where('role', 'admin')
            ->select('id', 'name', 'email', 'role', 'created_at')
            ->paginate(20);

        return response()->json($admins);
    }

    // POST /api/super-admin/admins
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $admin = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'role'     => 'admin',
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Admin created successfully.',
            'admin'   => $admin->only('id', 'name', 'email', 'role', 'created_at'),
        ], 201);
    }

    // PATCH /api/super-admin/admins/{user}/promote
    // Promote a customer to admin
    public function promote(User $user): JsonResponse
    {
        if ($user->role !== 'customer') {
            return response()->json(['message' => 'Only customers can be promoted to admin.'], 422);
        }

        $user->update(['role' => 'admin']);

        return response()->json([
            'message' => 'User promoted to admin.',
            'user'    => $user->only('id', 'name', 'email', 'role'),
        ]);
    }

    // PATCH /api/super-admin/admins/{user}/demote
    // Demote an admin back to customer
    public function demote(User $user): JsonResponse
    {
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Only admins can be demoted.'], 422);
        }

        $user->update(['role' => 'customer']);

        return response()->json([
            'message' => 'Admin demoted to customer.',
            'user'    => $user->only('id', 'name', 'email', 'role'),
        ]);
    }

    // DELETE /api/super-admin/admins/{user}
    public function destroy(User $user): JsonResponse
    {
        if ($user->role === 'super_admin') {
            return response()->json(['message' => 'Cannot delete a super admin.'], 403);
        }

        // Revoke all tokens before deleting
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Admin deleted successfully.']);
    }
}
