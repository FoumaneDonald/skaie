<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Snapshot de l'adresse de livraison au moment de la commande
            $table->string('shipping_name');
            $table->string('shipping_street');
            $table->string('shipping_city');
            $table->string('shipping_state')->nullable();
            $table->string('shipping_zip')->nullable();
            $table->string('shipping_country')->default('CM');
            $table->string('shipping_phone')->nullable();

            $table->decimal('subtotal', 10, 2);       // somme des articles
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);

            // pending → processing → shipped → delivered | cancelled
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])
                  ->default('pending');

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
