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
        Schema::create('auth_tokens', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->string('type'); // admin | user

            $table->string('token', 64)->unique(); // sha256
            $table->boolean('is_active')->default(true);

            $table->timestamp('expired_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // 🔥 จำกัด 1 active token ต่อ user
            $table->unique(['user_id', 'type', 'is_active']);
            $table->index(['token', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
