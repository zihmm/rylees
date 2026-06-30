<?php

declare(strict_types=1);

namespace App\Modules\Project\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProjectRequest extends FormRequest
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
            'name' => 'required|string',
            'description' => 'nullable|string',
            'language' => 'sometimes|in:de,en,fr',
            'llm_tonality_id' => 'required|uuid|exists:llm_tonality_types,id',
            'llm_temperature_id' => 'required|uuid|exists:llm_temperature_types,id',
        ];
    }
}
