<?php

declare(strict_types=1);

namespace App\Modules\Customer\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreContactRequest extends FormRequest
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
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'email' => 'required|email',
        ];
    }
}
