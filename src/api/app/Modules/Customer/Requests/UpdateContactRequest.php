<?php

declare(strict_types=1);

namespace App\Modules\Customer\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateContactRequest extends FormRequest
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
            'firstname' => 'sometimes|string',
            'lastname' => 'sometimes|string',
            'email' => 'sometimes|email',
        ];
    }
}
