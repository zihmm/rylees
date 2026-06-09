<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('organisation_id');
            $table->uuid('industry_id')->nullable();
            $table->uuid('main_contact_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->foreign('industry_id')->references('id')->on('industry_types');

            $table->index('user_id', 'customers_user_id_index');
            $table->index('organisation_id', 'customers_organisation_id_index');
            $table->index('industry_id', 'customers_industry_id_index');
            $table->index('main_contact_id', 'customers_main_contact_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
