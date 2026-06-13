<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void
        {
            $table->string('password_reset_token', 255)->nullable()->after('activated_at');
            $table->timestamp('password_reset_expires_at')->nullable()->after('password_reset_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void
        {
            $table->dropColumn(['password_reset_token', 'password_reset_expires_at']);
        });
    }
};
