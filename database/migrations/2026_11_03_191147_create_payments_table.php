<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('provider', 32);
            $table->string('method')->nullable();
            $table->string('status')->default('open');
            $table->string('failure_reason')->nullable();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->unsignedBigInteger('fee')->nullable();
            $table->string('provider_checkout_id')->nullable();
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_status')->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_checkout_id']);
            $table->index(['provider', 'provider_payment_id']);
            $table->index(['status', 'expires_at']);
            $table->index(['method', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
