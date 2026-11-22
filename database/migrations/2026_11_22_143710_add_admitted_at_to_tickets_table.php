<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a ticket's holder was let in at the gate, and by whom. Separate from the headset hand-over, which is
     * `checked_in_at`, so a ticket can't get a second person in once it's been scanned.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('admitted_at')->nullable()->after('checked_in_by');
            $table->foreignId('admitted_by')->nullable()->after('admitted_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admitted_by');
            $table->dropColumn('admitted_at');
        });
    }
};
