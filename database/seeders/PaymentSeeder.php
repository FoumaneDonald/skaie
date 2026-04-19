<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Génère des commandes et paiements de test (sandbox).
 * Utile pour tester le dashboard admin sans passer par Stripe.
 *
 * Lancer avec : php artisan db:seed --class=PaymentSeeder
 */
class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        // Récupérer des clients existants (ou en créer)
        $customers = User::where('role', 'customer')->limit(3)->get();

        if ($customers->isEmpty()) {
            $this->command->warn('Aucun client trouvé. Créez d\'abord des clients.');
            return;
        }

        $products = Product::where('is_active', true)->limit(10)->get();

        if ($products->isEmpty()) {
            $this->command->warn('Aucun produit actif trouvé.');
            return;
        }

        $statuses  = ['pending', 'processing', 'shipped', 'delivered'];
        $payStatus = ['succeeded', 'succeeded', 'succeeded', 'failed', 'pending'];

        foreach ($customers as $customer) {
            for ($i = 0; $i < 4; $i++) {
                DB::transaction(function () use ($customer, $products, $statuses, $payStatus, $i) {
                    // Choisir 1 à 3 produits aléatoires
                    $picked   = $products->random(rand(1, 3));
                    $subtotal = 0;
                    $lines    = [];

                    foreach ($picked as $product) {
                        $qty          = rand(1, 3);
                        $lineTotal    = round($product->price * $qty, 2);
                        $subtotal    += $lineTotal;
                        $lines[]      = compact('product', 'qty', 'lineTotal');
                    }

                    $shippingFee = $subtotal >= 50 ? 0 : ($subtotal >= 20 ? 3.99 : 5.99);
                    $total       = round($subtotal + $shippingFee, 2);

                    $orderStatus = $statuses[array_rand($statuses)];

                    $order = Order::create([
                        'user_id'          => $customer->id,
                        'shipping_name'    => $customer->name,
                        'shipping_street'  => '123 Rue de Test',
                        'shipping_city'    => 'Yaoundé',
                        'shipping_country' => 'CM',
                        'subtotal'         => $subtotal,
                        'shipping_fee'     => $shippingFee,
                        'total'            => $total,
                        'status'           => $orderStatus,
                        'created_at'       => now()->subDays(rand(0, 60)),
                    ]);

                    foreach ($lines as $line) {
                        OrderItem::create([
                            'order_id'     => $order->id,
                            'product_id'   => $line['product']->id,
                            'product_name' => $line['product']->name,
                            'unit_price'   => $line['product']->price,
                            'quantity'     => $line['qty'],
                            'subtotal'     => $line['lineTotal'],
                        ]);
                    }

                    // Créer un paiement fictif (sandbox)
                    $pStatus = $payStatus[array_rand($payStatus)];
                    Payment::create([
                        'order_id'                 => $order->id,
                        'user_id'                  => $customer->id,
                        'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
                        'amount'                   => $total,
                        'currency'                 => 'eur',
                        'status'                   => $pStatus,
                        'paid_at'                  => $pStatus === 'succeeded'
                            ? $order->created_at->addMinutes(rand(2, 30))
                            : null,
                        'created_at'               => $order->created_at,
                    ]);
                });
            }
        }

        $this->command->info('PaymentSeeder terminé : commandes et paiements de test créés.');
    }
}
