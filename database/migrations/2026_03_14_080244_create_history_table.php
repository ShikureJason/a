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
        Schema::create('history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('beacon_location');
            $table->foreignId('user_id')->constrained('account');
            $table->integer('first_active');
            $table->integer('rsci_avg');
            $table->integer('time_duration');
            $table->dateTime('created_at', precision: 0);
            $table->dateTime('closed_at', precision: 0);
            $table->dateTime('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('history');
    }
};
