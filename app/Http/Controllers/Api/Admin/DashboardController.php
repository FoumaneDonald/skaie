<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard
     *
     * Vue d'ensemble : chiffres clés, revenus, commandes récentes,
     * produits les plus vendus, évolution mensuelle.
     */
    public function index(Request $request): JsonResponse
    {
        $period = (int) $request->get('days', 30); // fenêtre configurable (défaut 30 jours)
        $since  = now()->subDays($period);

        // ── KPIs globaux ─────────────────────────────────────────
        $totalRevenue = Payment::where('status', 'succeeded')->sum('amount');

        $revenueThisPeriod = Payment::where('status', 'succeeded')
            ->where('paid_at', '>=', $since)
            ->sum('amount');

        $totalOrders        = Order::count();
        $ordersThisPeriod   = Order::where('created_at', '>=', $since)->count();

        $totalCustomers     = User::where('role', 'customer')->count();
        $newCustomers       = User::where('role', 'customer')
                                  ->where('created_at', '>=', $since)
                                  ->count();

        $pendingOrders      = Order::where('status', 'pending')->count();
        $processingOrders   = Order::where('status', 'processing')->count();

        $failedPayments     = Payment::where('status', 'failed')
                                     ->where('created_at', '>=', $since)
                                     ->count();

        // ── Répartition des commandes par statut ─────────────────
        $ordersByStatus = Order::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // ── Répartition des paiements par statut ─────────────────
        $paymentsByStatus = Payment::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // ── Revenus par jour sur la période ──────────────────────
        $dailyRevenue = Payment::select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as transactions')
            )
            ->where('status', 'succeeded')
            ->where('paid_at', '>=', $since)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // ── Produits les plus vendus ──────────────────────────────
        $topProducts = DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $since)
            ->whereIn('orders.status', ['processing', 'shipped', 'delivered'])
            ->select(
                'products.id',
                'products.name',
                'products.category',
                'products.image',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.category', 'products.image')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        // ── 10 dernières commandes ────────────────────────────────
        $recentOrders = Order::with(['user:id,name,email', 'payment:id,order_id,status,amount,paid_at'])
            ->latest()
            ->limit(10)
            ->get(['id', 'user_id', 'total', 'status', 'created_at']);

        // ── Produits à faible stock (≤ 5 unités) ─────────────────
        $lowStockProducts = Product::where('is_active', true)
            ->where('stock', '<=', 5)
            ->orderBy('stock')
            ->limit(10)
            ->get(['id', 'name', 'category', 'stock']);

        return response()->json([
            'period_days' => $period,

            'kpis' => [
                'total_revenue'      => round($totalRevenue, 2),
                'revenue_period'     => round($revenueThisPeriod, 2),
                'total_orders'       => $totalOrders,
                'orders_period'      => $ordersThisPeriod,
                'total_customers'    => $totalCustomers,
                'new_customers'      => $newCustomers,
                'pending_orders'     => $pendingOrders,
                'processing_orders'  => $processingOrders,
                'failed_payments'    => $failedPayments,
            ],

            'orders_by_status'   => $ordersByStatus,
            'payments_by_status' => $paymentsByStatus,
            'daily_revenue'      => $dailyRevenue,
            'top_products'       => $topProducts,
            'recent_orders'      => $recentOrders,
            'low_stock_products' => $lowStockProducts,
        ]);
    }

    /**
     * GET /api/admin/dashboard/revenue-summary
     *
     * Résumé des revenus par mois (12 derniers mois).
     * Utile pour le graphique de l'année dans le frontend Angular.
     */
    public function revenueSummary(): JsonResponse
    {
        $monthly = Payment::select(
                DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as month"),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as transactions')
            )
            ->where('status', 'succeeded')
            ->where('paid_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json(['monthly_revenue' => $monthly]);
    }
}
