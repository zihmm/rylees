<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_notes', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->uuid('release_history_id');
            $table->uuid('author_id');
            $table->text('body');
            $table->integer('version_major');
            $table->integer('version_minor');
            $table->integer('version_patch');
            $table->string('branch_name', 255)->nullable();
            $table->char('commithash_start', 64)->nullable();
            $table->char('commithash_end', 64)->nullable();
            $table->string('tag_start', 255)->nullable();
            $table->string('tag_end', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('release_history_id')->references('id')->on('release_histories');
            $table->foreign('author_id')->references('id')->on('users');

            $table->index('release_history_id', 'release_notes_release_history_id_index');
            $table->index('author_id', 'release_notes_author_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_notes');
    }
};
