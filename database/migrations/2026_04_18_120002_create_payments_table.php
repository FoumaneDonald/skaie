<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Stripe identifiers
            $table->string('stripe_payment_intent_id')->unique()->nullable();
            $table->string('stripe_client_secret')->nullable();   // envoyé au frontend

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('eur');       // Stripe sandbox: EUR/USD

            // requires_payment_method → processing → succeeded | failed | cancelled
            $table->enum('status', [
                'pending',
                'processing',
                'succeeded',
                'failed',
                'cancelled',
                'refunded',
            ])->default('pending');

            $table->string('failure_message')->nullable();        // message d'erreur Stripe
            $table->json('stripe_metadata')->nullable();          // données brutes webhook

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
