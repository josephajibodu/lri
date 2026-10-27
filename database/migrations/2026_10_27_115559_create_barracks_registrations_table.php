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
        Schema::create('barracks_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('state_of_origin');
            $table->string('lga');
            $table->string('residential_address');
            $table->string('occupation');
            $table->string('phone')->unique();
            $table->string('passport_path');
            $table->string('signature_name');
            $table->timestamp('declared_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barracks_registrations');
    }
};
