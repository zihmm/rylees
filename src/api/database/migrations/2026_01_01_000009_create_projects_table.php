<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('name', 255);
            $table->string('key', 255);
            $table->text('description')->nullable();
            $table->string('token', 64)->unique();
            $table->uuid('llm_tonality_id');
            $table->uuid('llm_temperature_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('llm_tonality_id')->references('id')->on('llm_tonality_types');
            $table->foreign('llm_temperature_id')->references('id')->on('llm_temperature_types');

            $table->index('customer_id', 'projects_customer_id_index');
            $table->index('llm_tonality_id', 'projects_llm_tonality_id_index');
            $table->index('llm_temperature_id', 'projects_llm_temperature_id_index');

            $table->unique(['customer_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
