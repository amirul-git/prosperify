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
        Schema::create('donation_food_diffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_food_id')->constrained(
                table: 'donation_food',
            );
            $table->foreignId('donation_id')->constrained();
            $table->foreignId('food_id')->constrained();
            $table->integer('amount');
            $table->unsignedBigInteger('on_food_donation_status_id');
            $table->foreignId('food_donation_status_id')->constrained();
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donation_food_diffs');
    }
};
