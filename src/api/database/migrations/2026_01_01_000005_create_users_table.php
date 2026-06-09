<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->string('username', 255)->unique();
            $table->string('password', 255);
            $table->string('api_key', 64)->unique();
            $table->boolean('is_active')->default(false);
            $table->string('activation_token', 255)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
