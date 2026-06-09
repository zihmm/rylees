<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_histories', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('project_id')->references('id')->on('projects');
            $table->index('project_id', 'release_histories_project_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_histories');
    }
};
