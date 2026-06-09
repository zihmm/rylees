<?php

declare(strict_types=1);

namespace App\Modules\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAccountRequest extends FormRequest
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
            'profile.firstname' => 'sometimes|string',
            'profile.lastname' => 'sometimes|string',
            'organisation.name' => 'sometimes|string',
            'organisation.street' => 'sometimes|nullable|string',
            'organisation.postcode' => 'sometimes|nullable|string',
            'organisation.city' => 'sometimes|nullable|string',
            'organisation.website' => 'sometimes|nullable|string',
            'organisation.email' => 'sometimes|nullable|email',
            'current_password' => 'required_with:new_password|string',
            'new_password' => 'sometimes|string|min:12',
        ];
    }
}
