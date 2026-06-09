<?php

declare(strict_types=1);

namespace App\Modules\Customer\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCustomerRequest extends FormRequest
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
            'organisation.name' => 'sometimes|string',
            'organisation.street' => 'sometimes|nullable|string',
            'organisation.postcode' => 'sometimes|nullable|string',
            'organisation.city' => 'sometimes|nullable|string',
            'organisation.website' => 'sometimes|nullable|string',
            'organisation.email' => 'sometimes|nullable|email',
            'industry_id' => 'sometimes|nullable|uuid|exists:industry_types,id',
            'description' => 'sometimes|nullable|string',
        ];
    }
}
