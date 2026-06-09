<?php

declare(strict_types=1);

namespace App\Modules\Customer\Repositories;

use App\Modules\Customer\Models\CustomerContact;

final class ContactRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CustomerContact
    {
        return CustomerContact::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CustomerContact $contact, array $attributes): CustomerContact
    {
        $contact->update($attributes);

        return $contact;
    }

    public function delete(CustomerContact $contact): void
    {
        $contact->delete();
    }
}
