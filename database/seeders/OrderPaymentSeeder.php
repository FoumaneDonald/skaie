<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Génère des commandes et paiements fictifs pour tester le module.
 *
 * php artisan db:seed --class=OrderPaymentSeeder
 */
class OrderPaymentSeeder extends Seeder
{
    public function run(): void
    {
        // Récupérer un customer (ou en créer un)
        $customer = User::where('role', 'customer')->first();

        if (!$customer) {
            $customer = User::create([
                'name'              => 'Client Test',
                'email'             => 'client@skaie.test',
                'password'          => bcrypt('password'),
                'role'              => 'customer',
                'email_verified_at' => now(),
            ]);
        }

        // Récupérer des produits actifs
        $products = Product::where('is_active', true)->take(4)->get();

        if ($products->isEmpty()) {
            $this->command->warn('Aucun produit actif trouvé. Lance d\'abord un ProductSeeder.');
            return;
        }

        // ── Commande 1 : payée (succeeded) ───────────────────────
        $order1 = $this->createOrder($customer, $products, 'processing');
        Payment::create([
            'order_id'                 => $order1->id,
            'user_id'                  => $customer->id,
            'stripe_payment_intent_id' => 'pi_test_succeeded_' . uniqid(),
            'amount'                   => $order1->total,
            'currency'                 => 'eur',
            'status'                   => 'succeeded',
            'paid_at'                  => now()->subDays(2),
        ]);

        // ── Commande 2 : en attente de paiement ──────────────────
        $order2 = $this->createOrder($customer, $products, 'pending');
        Payment::create([
            'order_id'                 => $order2->id,
            'user_id'                  => $customer->id,
            'stripe_payment_intent_id' => 'pi_test_pending_' . uniqid(),
            'stripe_client_secret'     => 'pi_test_secret_' . uniqid(),
            'amount'                   => $order2->total,
            'currency'                 => 'eur',
            'status'                   => 'pending',
        ]);

        // ── Commande 3 : paiement échoué ─────────────────────────
        $order3 = $this->createOrder($customer, $products, 'cancelled');
        Payment::create([
            'order_id'                 => $order3->id,
            'user_id'                  => $customer->id,
            'stripe_payment_intent_id' => 'pi_test_failed_' . uniqid(),
            'amount'                   => $order3->total,
            'currency'                 => 'eur',
            'status'                   => 'failed',
            'failure_message'          => 'Your card was declined.',
        ]);

        $this->command->info("✅ 3 commandes de test créées pour {$customer->email}");
    }

    private function createOrder(User $customer, $products, string $status): Order
    {
        $product = $products->random();
        $qty     = rand(1, 3);
        $subtotal = round($product->price * $qty, 2);
        $fee      = $subtotal >= 50 ? 0 : ($subtotal >= 20 ? 3.99 : 5.99);
        $total    = round($subtotal + $fee, 2);

        $order = Order::create([
            'user_id'          => $customer->id,
            'shipping_name'    => 'Domicile',
            'shipping_street'  => '12 rue de la Paix',
            'shipping_city'    => 'Yaoundé',
            'shipping_country' => 'CM',
            'shipping_phone'   => '+237600000000',
            'subtotal'         => $subtotal,
            'shipping_fee'     => $fee,
            'total'            => $total,
            'status'           => $status,
        ]);

        OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'unit_price'   => $product->price,
            'quantity'     => $qty,
            'subtotal'     => $subtotal,
        ]);

        return $order;
    }
}
