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
        Schema::create('beacon', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->unique();
            $table->string('secret');
            $table->boolean('revoked')->default(false);
            $table->foreignId('location_id')->constrained('beacon_location');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beacon');
    }
};
