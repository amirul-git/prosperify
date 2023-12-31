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
        Schema::create('food_donation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->unsignedBigInteger('food_donation_status_id');
            $table->string('food_donation_status_name');
            $table->integer('amount');
            $table->unsignedBigInteger('unit_id');
            $table->string('unit_name');
            $table->string('photo');
            $table->foreignId('donation_food_id')->constrained(
                table: 'donation_food',
            )->cascadeOnDelete();
            $table->integer('stored_food_amount');
            $table->foreignId('donation_id');
            $table->foreignId('food_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_donation_logs');
    }
};
