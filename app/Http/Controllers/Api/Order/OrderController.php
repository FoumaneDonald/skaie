<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    //  CUSTOMER
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/customer/orders
     * Liste des commandes du client connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with(['items', 'payment'])
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    /**
     * GET /api/customer/orders/{order}
     * Détail d'une commande (appartient au client connecté).
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);

        return response()->json($order->load(['items.product', 'payment']));
    }

    /**
     * POST /api/customer/orders
     * Créer une commande depuis le panier envoyé.
     *
     * Body:
     * {
     *   "address_id": 3,           // ID d'une adresse sauvegardée (optionnel si address_data fourni)
     *   "address_data": { ... },   // adresse manuelle (optionnel si address_id fourni)
     *   "notes": "...",
     *   "items": [
     *     { "product_id": 1, "quantity": 2 },
     *     { "product_id": 4, "quantity": 1 }
     *   ]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address_id'              => 'nullable|integer|exists:addresses,id',
            'address_data'            => 'nullable|array',
            'address_data.label'      => 'required_with:address_data|string|max:100',
            'address_data.street'     => 'required_with:address_data|string|max:255',
            'address_data.city'       => 'required_with:address_data|string|max:100',
            'address_data.state'      => 'nullable|string|max:100',
            'address_data.zip'        => 'nullable|string|max:20',
            'address_data.country'    => 'nullable|string|max:2',
            'address_data.phone'      => 'nullable|string|max:30',
            'notes'                   => 'nullable|string|max:500',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|integer|exists:products,id',
            'items.*.quantity'        => 'required|integer|min:1|max:100',
        ]);

        // ── Résoudre l'adresse de livraison ──────────────────────
        if (!empty($data['address_id'])) {
            $address = Address::where('id', $data['address_id'])
                              ->where('user_id', $request->user()->id)
                              ->firstOrFail();
            $shippingData = Order::snapshotAddress($address);
        } elseif (!empty($data['address_data'])) {
            $ad = $data['address_data'];
            $shippingData = [
                'shipping_name'    => $ad['label'],
                'shipping_street'  => $ad['street'],
                'shipping_city'    => $ad['city'],
                'shipping_state'   => $ad['state']   ?? null,
                'shipping_zip'     => $ad['zip']      ?? null,
                'shipping_country' => $ad['country']  ?? 'CM',
                'shipping_phone'   => $ad['phone']    ?? null,
            ];
        } else {
            return response()->json([
                'message' => 'Veuillez fournir une adresse de livraison (address_id ou address_data).',
            ], 422);
        }

        // ── Vérification du stock & calcul des totaux ─────────────
        $productIds = array_column($data['items'], 'product_id');
        $products   = Product::whereIn('id', $productIds)
                             ->where('is_active', true)
                             ->get()
                             ->keyBy('id');

        $orderLines = [];
        $subtotal   = 0;

        foreach ($data['items'] as $line) {
            $product = $products->get($line['product_id']);

            if (!$product) {
                return response()->json([
                    'message' => "Le produit #{$line['product_id']} est introuvable ou inactif.",
                ], 422);
            }

            if ($product->stock < $line['quantity']) {
                return response()->json([
                    'message' => "Stock insuffisant pour « {$product->name} » (dispo : {$product->stock}).",
                ], 422);
            }

            $lineSubtotal = round($product->price * $line['quantity'], 2);
            $subtotal    += $lineSubtotal;

            $orderLines[] = [
                'product'      => $product,
                'product_name' => $product->name,
                'unit_price'   => $product->price,
                'quantity'     => $line['quantity'],
                'subtotal'     => $lineSubtotal,
            ];
        }

        $shippingFee = $this->computeShippingFee($subtotal);
        $total       = round($subtotal + $shippingFee, 2);

        // ── Tout en transaction ───────────────────────────────────
        $order = DB::transaction(function () use ($request, $shippingData, $data, $orderLines, $subtotal, $shippingFee, $total) {
            $order = Order::create([
                'user_id'      => $request->user()->id,
                ...$shippingData,
                'subtotal'     => $subtotal,
                'shipping_fee' => $shippingFee,
                'total'        => $total,
                'status'       => 'pending',
                'notes'        => $data['notes'] ?? null,
            ]);

            foreach ($orderLines as $line) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $line['product']->id,
                    'product_name' => $line['product_name'],
                    'unit_price'   => $line['unit_price'],
                    'quantity'     => $line['quantity'],
                    'subtotal'     => $line['subtotal'],
                ]);

                // Décrémenter le stock
                $line['product']->decrement('stock', $line['quantity']);
            }

            return $order;
        });

        return response()->json([
            'message' => 'Commande créée. Procédez au paiement.',
            'order'   => $order->load('items'),
        ], 201);
    }

    /**
     * DELETE /api/customer/orders/{order}
     * Annuler une commande (seulement si pending et non payée).
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);

        if (!$order->isPending()) {
            return response()->json([
                'message' => 'Seules les commandes en attente peuvent être annulées.',
            ], 409);
        }

        if ($order->isPaid()) {
            return response()->json([
                'message' => 'La commande est déjà payée. Contactez le support pour un remboursement.',
            ], 409);
        }

        DB::transaction(function () use ($order) {
            // Remettre le stock
            foreach ($order->items as $item) {
                $item->product?->increment('stock', $item->quantity);
            }
            $order->update(['status' => 'cancelled']);
        });

        return response()->json(['message' => 'Commande annulée avec succès.']);
    }

    // ─────────────────────────────────────────────────────────────
    //  ADMIN
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/orders
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'items', 'payment'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json($query->paginate(15));
    }

    /**
     * GET /api/admin/orders/{order}
     */
    public function adminShow(Order $order): JsonResponse
    {
        return response()->json($order->load(['user', 'items.product', 'payment']));
    }

    /**
     * PATCH /api/admin/orders/{order}/status
     * Mettre à jour le statut d'une commande.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:processing,shipped,delivered,cancelled',
        ]);

        if ($order->isCancelled()) {
            return response()->json(['message' => 'Cette commande est déjà annulée.'], 409);
        }

        $order->update(['status' => $data['status']]);

        return response()->json([
            'message' => 'Statut mis à jour.',
            'order'   => $order->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers privés
    // ─────────────────────────────────────────────────────────────

    private function authorizeOrder(Request $request, Order $order): void
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403, 'Accès non autorisé.');
        }
    }

    /** Frais de livraison simples (à adapter selon vos règles métier). */
    private function computeShippingFee(float $subtotal): float
    {
        if ($subtotal >= 50) return 0;      // livraison gratuite au-delà de 50 €
        if ($subtotal >= 20) return 3.99;
        return 5.99;
    }
}
