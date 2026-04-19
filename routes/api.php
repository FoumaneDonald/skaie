<?php

use App\Http\Controllers\Api\SuperAdmin\AdminManagerController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\Customer\AddressController;
use App\Http\Controllers\Api\Order\OrderController;
use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;



// ══════════════════════════════════════════════════════════════
//  AUTH (public, rate limited)
// ══════════════════════════════════════════════════════════════
Route::middleware('throttle:10,1')->prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('refresh',  [AuthController::class, 'refresh']);
});

Route::middleware('auth:api')->prefix('auth')->group(function () {
    Route::post('logout',        [AuthController::class, 'logout']);
    Route::get ('me',            [AuthController::class, 'me']);
    Route::post('email/verify',  [AuthController::class, 'verifyEmail']);
    Route::post('email/resend',  [AuthController::class, 'resendOtp']);
});


// ══════════════════════════════════════════════════════════════
//  CUSTOMER
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth:api', 'verified.api', 'role:customer'])->prefix('customer')->group(function () {

    // ── Profil & adresses ──────────────────────────────────────
    Route::get   ('profile',          [ProfileController::class, 'show']);
    Route::patch ('profile',          [ProfileController::class, 'update']);
    Route::patch ('profile/password', [ProfileController::class, 'changePassword']);
    Route::delete('profile',          [ProfileController::class, 'destroy']);

    Route::get   ('addresses',                   [AddressController::class, 'index']);
    Route::post  ('addresses',                   [AddressController::class, 'store']);
    Route::patch ('addresses/{address}',         [AddressController::class, 'update']);
    Route::patch ('addresses/{address}/default', [AddressController::class, 'setDefault']);
    Route::delete('addresses/{address}',         [AddressController::class, 'destroy']);

    // ── Produits (lecture) ─────────────────────────────────────
    Route::apiResource('products', ProductController::class);

    // ── Commandes ──────────────────────────────────────────────
    Route::get   ('orders',                [OrderController::class, 'index']);
    Route::post  ('orders',                [OrderController::class, 'store']);
    Route::get   ('orders/{order}',        [OrderController::class, 'show']);
    Route::delete('orders/{order}/cancel', [OrderController::class, 'cancel']);

    // ── Paiements ──────────────────────────────────────────────
    Route::get ('payments',                      [PaymentController::class, 'history']);
    Route::post('orders/{order}/payment',         [PaymentController::class, 'initiate']);
    Route::get ('orders/{order}/payment/status',  [PaymentController::class, 'status']);
});


// ══════════════════════════════════════════════════════════════
//  ADMIN
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth:api', 'verified.api', 'role:admin'])->prefix('admin')->group(function () {

    // Dashboard & stats
    Route::get('dashboard',                [DashboardController::class, 'index']);
    Route::get('dashboard/revenue-summary',[DashboardController::class, 'revenueSummary']);

    Route::get('users', fn() => \App\Models\User::where('role', 'customer')->get());

    // Commandes
    Route::get  ('orders',                [OrderController::class, 'adminIndex']);
    Route::get  ('orders/{order}',        [OrderController::class, 'adminShow']);
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);

    // Paiements
    Route::get ('payments',                  [PaymentController::class, 'adminIndex']);
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund']);
});


// ══════════════════════════════════════════════════════════════
//  SUPER ADMIN
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth:api', 'verified.api', 'role:super_admin'])->prefix('super-admin')->group(function () {
    Route::get   ('admins',                [AdminManagerController::class, 'index']);
    Route::post  ('admins',                [AdminManagerController::class, 'store']);
    Route::patch ('admins/{user}/promote', [AdminManagerController::class, 'promote']);
    Route::patch ('admins/{user}/demote',  [AdminManagerController::class, 'demote']);
    Route::delete('admins/{user}',         [AdminManagerController::class, 'destroy']);
});


// ══════════════════════════════════════════════════════════════
//  STRIPE WEBHOOK  (public — pas de auth:api !)
// ══════════════════════════════════════════════════════════════
Route::post('stripe/webhook', [PaymentController::class, 'webhook'])
    ->middleware('throttle:60,1');
