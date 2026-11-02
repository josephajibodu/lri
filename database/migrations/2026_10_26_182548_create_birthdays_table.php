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
        Schema::create('birthdays', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('preferred_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('instagram_handle')->nullable();
            $table->string('tribe')->nullable();
            $table->unsignedTinyInteger('birth_month');
            $table->unsignedTinyInteger('birth_day');
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->text('notes')->nullable();
            $table->json('photo_paths')->nullable();
            $table->boolean('photo_consent')->default(false);
            $table->timestamps();

            $table->index(['birth_month', 'birth_day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('birthdays');
    }
};
