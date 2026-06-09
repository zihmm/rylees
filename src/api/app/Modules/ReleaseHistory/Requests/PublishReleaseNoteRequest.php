<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PublishReleaseNoteRequest extends FormRequest
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
            'startRef' => 'required|string',
            'endRef' => 'required|string',
            'type' => 'required|in:commits,tag',
            'branchName' => 'nullable|string',
            'body' => 'required|string',
            'versionBump' => 'required|in:major,minor,patch',
        ];
    }
}
