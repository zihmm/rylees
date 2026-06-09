<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('firstname', 255);
            $table->string('lastname', 255);
            $table->uuid('organisation_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->index('organisation_id', 'user_profiles_organisation_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
