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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('note')->nullable();
            $table->string('discount_type');
            $table->unsignedBigInteger('discount_value');
            $table->char('currency', 3)->default('NGN');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_orders')->nullable();
            $table->unsignedSmallInteger('max_tickets_per_order')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coupon_ticket_type', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();

            $table->primary(['coupon_id', 'ticket_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_ticket_type');
        Schema::dropIfExists('coupons');
    }
};
