<?php

declare(strict_types=1);

namespace App\Modules\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
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
            'username' => 'required|email|unique:users,username',
            'password' => 'required|string|min:12',
            'profile.firstname' => 'required|string',
            'profile.lastname' => 'required|string',
            'organisation.name' => 'required|string',
            'organisation.street' => 'nullable|string',
            'organisation.postcode' => 'nullable|string',
            'organisation.city' => 'nullable|string',
            'organisation.website' => 'nullable|string',
            'organisation.email' => 'nullable|email',
        ];
    }
}
