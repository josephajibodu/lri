<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Give each ticket its holder's email, a private link of its own, and who handed over its headset.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('holder_email')->nullable()->after('holder_name');
            $table->string('claim_token', 64)->nullable()->unique()->after('code');
            $table->foreignId('checked_in_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();

            $table->index('holder_email');
        });

        DB::table('tickets')->whereNull('claim_token')->orderBy('id')->each(function (object $ticket): void {
            DB::table('tickets')->where('id', $ticket->id)->update(['claim_token' => Str::random(40)]);
        });

        DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->whereNull('tickets.holder_email')
            ->whereColumn('tickets.holder_name', 'orders.buyer_name')
            ->select('tickets.id', 'orders.buyer_email')
            ->orderBy('tickets.id')
            ->each(function (object $ticket): void {
                DB::table('tickets')->where('id', $ticket->id)->update(['holder_email' => $ticket->buyer_email]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_in_by');
            $table->dropIndex(['holder_email']);
            $table->dropUnique(['claim_token']);
            $table->dropColumn(['holder_email', 'claim_token']);
        });
    }
};
