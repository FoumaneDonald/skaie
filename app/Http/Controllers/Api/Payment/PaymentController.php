<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class PaymentController extends Controller
{
    public function __construct()
    {
        // Initialiser Stripe avec la clé secrète (sandbox = sk_test_...)
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    // ─────────────────────────────────────────────────────────────
    //  CUSTOMER
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/customer/orders/{order}/payment
     *
     * Crée (ou récupère) un PaymentIntent Stripe pour cette commande.
     * Retourne le client_secret au frontend Angular pour finaliser
     * le paiement avec Stripe.js / Elements.
     */
    public function initiate(Request $request, Order $order): JsonResponse
    {
        // Vérifier que la commande appartient au client connecté
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        if ($order->isCancelled()) {
            return response()->json(['message' => 'Cette commande est annulée.'], 409);
        }

        // Vérifier si un paiement réussi existe déjà
        if ($order->payment?->isSucceeded()) {
            return response()->json(['message' => 'Cette commande est déjà payée.'], 409);
        }

        try {
            // ── Réutiliser le PaymentIntent existant (idempotence) ──
            if ($order->payment && $order->payment->stripe_payment_intent_id) {
                $intent = PaymentIntent::retrieve($order->payment->stripe_payment_intent_id);

                // Si l'intent est annulé ou échoué, en créer un nouveau
                if (in_array($intent->status, ['canceled', 'succeeded'])) {
                    $intent = $this->createPaymentIntent($order, $request->user());
                    $order->payment->update([
                        'stripe_payment_intent_id' => $intent->id,
                        'stripe_client_secret'     => $intent->client_secret,
                        'status'                   => 'pending',
                        'failure_message'          => null,
                    ]);
                }
            } else {
                // ── Premier paiement : créer l'intent et le Payment ──
                $intent = $this->createPaymentIntent($order, $request->user());

                Payment::create([
                    'order_id'                 => $order->id,
                    'user_id'                  => $request->user()->id,
                    'stripe_payment_intent_id' => $intent->id,
                    'stripe_client_secret'     => $intent->client_secret,
                    'amount'                   => $order->total,
                    'currency'                 => config('services.stripe.currency', 'eur'),
                    'status'                   => 'pending',
                ]);
            }

            return response()->json([
                'message'       => 'PaymentIntent créé. Finalisez le paiement avec Stripe.js.',
                'client_secret' => $intent->client_secret,  // envoyé au frontend
                'amount'        => $order->total,
                'currency'      => config('services.stripe.currency', 'eur'),
                'order_id'      => $order->id,
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Stripe error on initiate', ['error' => $e->getMessage(), 'order' => $order->id]);
            return response()->json(['message' => 'Erreur Stripe : ' . $e->getMessage()], 502);
        }
    }

    /**
     * GET /api/customer/orders/{order}/payment/status
     *
     * Vérifie l'état du paiement depuis notre base de données.
     * Le frontend peut poller cette route après la confirmation Stripe.
     */
    public function status(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $payment = $order->payment;

        if (!$payment) {
            return response()->json(['message' => 'Aucun paiement initié pour cette commande.'], 404);
        }

        return response()->json([
            'order_id'       => $order->id,
            'order_status'   => $order->status,
            'payment_status' => $payment->status,
            'amount'         => $payment->amount,
            'currency'       => $payment->currency,
            'paid_at'        => $payment->paid_at,
            'failure_message'=> $payment->failure_message,
        ]);
    }

    /**
     * GET /api/customer/payments
     * Historique des paiements du client connecté.
     */
    public function history(Request $request): JsonResponse
    {
        $payments = $request->user()
            ->payments()
            ->with('order:id,status,total,created_at')
            ->latest()
            ->paginate(10);

        return response()->json($payments);
    }

    // ─────────────────────────────────────────────────────────────
    //  ADMIN
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/payments
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Payment::with(['user:id,name,email', 'order:id,status,total,created_at'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(15));
    }

    /**
     * POST /api/admin/payments/{payment}/refund
     * Déclencher un remboursement Stripe.
     */
    public function refund(Payment $payment): JsonResponse
    {
        if (!$payment->isSucceeded()) {
            return response()->json(['message' => 'Seuls les paiements réussis peuvent être remboursés.'], 409);
        }

        try {
            \Stripe\Refund::create([
                'payment_intent' => $payment->stripe_payment_intent_id,
            ]);

            $payment->update(['status' => 'refunded']);
            $payment->order->update(['status' => 'cancelled']);

            // Remettre le stock
            foreach ($payment->order->items as $item) {
                $item->product?->increment('stock', $item->quantity);
            }

            return response()->json(['message' => 'Remboursement effectué avec succès.']);

        } catch (ApiErrorException $e) {
            Log::error('Stripe refund error', ['error' => $e->getMessage(), 'payment' => $payment->id]);
            return response()->json(['message' => 'Erreur Stripe : ' . $e->getMessage()], 502);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  WEBHOOK Stripe (route publique, pas de auth:api)
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/stripe/webhook
     *
     * Stripe envoie les événements ici.
     * Configurer l'URL dans le Dashboard Stripe Sandbox.
     * En local : utiliser le Stripe CLI → stripe listen --forward-to localhost/api/stripe/webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        // Vérifier la signature Stripe (sécurité obligatoire)
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature invalid', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        Log::info('Stripe webhook received', ['type' => $event->type]);

        // Traiter les événements
        match ($event->type) {
            'payment_intent.succeeded'              => $this->handlePaymentSucceeded($event->data->object),
            'payment_intent.payment_failed'         => $this->handlePaymentFailed($event->data->object),
            'payment_intent.canceled'               => $this->handlePaymentCancelled($event->data->object),
            'charge.refunded'                       => $this->handleChargeRefunded($event->data->object),
            default                                 => null, // ignorer les autres événements
        };

        return response()->json(['received' => true]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Handlers Webhook privés
    // ─────────────────────────────────────────────────────────────

    private function handlePaymentSucceeded(object $intent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $intent->id)->first();
        if (!$payment) return;

        DB::transaction(function () use ($payment, $intent) {
            $payment->update([
                'status'           => 'succeeded',
                'paid_at'          => now(),
                'stripe_metadata'  => (array) $intent,
                'failure_message'  => null,
            ]);

            // Passer la commande en "processing" automatiquement
            $payment->order->update(['status' => 'processing']);
        });

        Log::info('Payment succeeded', ['order' => $payment->order_id, 'amount' => $payment->amount]);
    }

    private function handlePaymentFailed(object $intent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $intent->id)->first();
        if (!$payment) return;

        $failureMsg = $intent->last_payment_error?->message ?? 'Paiement refusé.';

        $payment->update([
            'status'          => 'failed',
            'failure_message' => $failureMsg,
            'stripe_metadata' => (array) $intent,
        ]);

        // Remettre le stock si le paiement échoue définitivement
        foreach ($payment->order->items as $item) {
            $item->product?->increment('stock', $item->quantity);
        }

        $payment->order->update(['status' => 'cancelled']);

        Log::warning('Payment failed', ['order' => $payment->order_id, 'reason' => $failureMsg]);
    }

    private function handlePaymentCancelled(object $intent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $intent->id)->first();
        if (!$payment) return;

        $payment->update([
            'status'          => 'cancelled',
            'stripe_metadata' => (array) $intent,
        ]);

        $payment->order->update(['status' => 'cancelled']);
    }

    private function handleChargeRefunded(object $charge): void
    {
        // Charge contient payment_intent ID
        $payment = Payment::where('stripe_payment_intent_id', $charge->payment_intent)->first();
        if (!$payment) return;

        $payment->update(['status' => 'refunded']);
        $payment->order->update(['status' => 'cancelled']);
    }

    // ─────────────────────────────────────────────────────────────
    //  Factory Stripe privé
    // ─────────────────────────────────────────────────────────────

    private function createPaymentIntent(Order $order, $user): PaymentIntent
    {
        $currency = config('services.stripe.currency', 'eur');

        // Stripe attend le montant en centimes (integer)
        $amountInCents = (int) round($order->total * 100);

        return PaymentIntent::create([
            'amount'               => $amountInCents,
            'currency'             => $currency,
            'description'          => "Commande #{$order->id} — Skaie",
            'receipt_email'        => $user->email,
            'metadata'             => [
                'order_id' => $order->id,
                'user_id'  => $user->id,
            ],
            // Activer les méthodes de paiement par carte
            'automatic_payment_methods' => ['enabled' => true],
        ]);
    }
}
