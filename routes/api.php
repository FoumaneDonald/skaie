<?php

use App\Http\Controllers\Api\SuperAdmin\AdminManagerController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\Customer\AddressController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;


Route::apiResource('products', ProductController::class);

// Public auth routes (rate limited)
Route::middleware('throttle:10,1')->prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('refresh',  [AuthController::class, 'refresh']);
});

// Authenticated routes
Route::middleware('auth:api')->prefix('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me',      [AuthController::class, 'me']);
    Route::post('email/verify',  [AuthController::class, 'verifyEmail']);
    Route::post('email/resend',  [AuthController::class, 'resendOtp']);
});

// Customer-only routes (placeholder for next modules)
Route::middleware(['auth:api', 'verified.api', 'role:customer'])->prefix('customer')->group(function () {
    // Profile
    Route::get   ('profile',          [ProfileController::class, 'show']);
    Route::patch ('profile',          [ProfileController::class, 'update']);
    Route::patch ('profile/password', [ProfileController::class, 'changePassword']);
    Route::delete('profile',          [ProfileController::class, 'destroy']);

    // Addresses
    Route::get   ('addresses',                   [AddressController::class, 'index']);
    Route::post  ('addresses',                   [AddressController::class, 'store']);
    Route::patch ('addresses/{address}',         [AddressController::class, 'update']);
    Route::patch ('addresses/{address}/default', [AddressController::class, 'setDefault']);
    Route::delete('addresses/{address}',         [AddressController::class, 'destroy']);
});

// Admin routes
Route::middleware(['auth:api', 'verified.api', 'role:admin'])->prefix('admin')->group(function () {
    // product management, etc.
    Route::get('users', fn() => \App\Models\User::where('role', 'customer')->get()); // read-only
});

// Super Admin routes
Route::middleware(['auth:api', 'verified.api', 'role:super_admin'])->prefix('super-admin')->group(function () {
    // admin management, system config...
    Route::get   ('admins',                  [AdminManagerController::class, 'index']);
    Route::post  ('admins',                  [AdminManagerController::class, 'store']);
    Route::patch ('admins/{user}/promote',   [AdminManagerController::class, 'promote']);
    Route::patch ('admins/{user}/demote',    [AdminManagerController::class, 'demote']);
    Route::delete('admins/{user}',           [AdminManagerController::class, 'destroy']);
});