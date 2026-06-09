<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_contacts', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('firstname', 255);
            $table->string('lastname', 255);
            $table->string('email', 255);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->index('customer_id', 'customer_contacts_customer_id_index');
        });

        // Add the deferred FK now that customer_contacts exists.
        // SQLite (test driver) cannot add a foreign key to an existing table,
        // so the constraint is only applied on drivers that support it.
        if (Schema::getConnection()->getDriverName() !== 'sqlite')
        {
            Schema::table('customers', function (Blueprint $table): void
            {
                $table->foreign('main_contact_id')->references('id')->on('customer_contacts');
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite')
        {
            Schema::table('customers', function (Blueprint $table): void
            {
                $table->dropForeign(['main_contact_id']);
            });
        }

        Schema::dropIfExists('customer_contacts');
    }
};
