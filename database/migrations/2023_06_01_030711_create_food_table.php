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
        Schema::create('food', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('detail');
            $table->timestamp('expired_date');
            $table->integer('amount');
            $table->enum('unit', ['kg', 'serving']);
            $table->timestamp('stored_timestamp')->nullable();
            $table->string('photo');
            $table->foreignId('user_id');
            $table->foreignId('category_id');
            $table->foreignId('sub_category_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food');
    }
};
