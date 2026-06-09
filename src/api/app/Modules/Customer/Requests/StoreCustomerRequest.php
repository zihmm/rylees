<?php

declare(strict_types=1);

namespace App\Modules\Customer\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'organisation.name' => 'required|string',
            'organisation.street' => 'nullable|string',
            'organisation.postcode' => 'nullable|string',
            'organisation.city' => 'nullable|string',
            'organisation.website' => 'nullable|string',
            'organisation.email' => 'nullable|email',
            'industry_id' => 'nullable|uuid|exists:industry_types,id',
            'description' => 'nullable|string',
            'main_contact.firstname' => 'required_with:main_contact|string',
            'main_contact.lastname' => 'required_with:main_contact|string',
            'main_contact.email' => 'required_with:main_contact|email',
        ];
    }
}
