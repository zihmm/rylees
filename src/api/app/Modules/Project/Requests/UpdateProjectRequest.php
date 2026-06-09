<?php

declare(strict_types=1);

namespace App\Modules\Project\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProjectRequest extends FormRequest
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
            'name' => 'sometimes|string',
            'description' => 'sometimes|nullable|string',
            'llm_tonality_id' => 'sometimes|uuid|exists:llm_tonality_types,id',
            'llm_temperature_id' => 'sometimes|uuid|exists:llm_temperature_types,id',
        ];
    }
}
