<?php

declare(strict_types=1);

namespace App\Modules\Project\Resources;

use App\Modules\Project\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
final class ProjectListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
            'description' => $this->description,
            'token' => $this->token,
            'llm' => [
                'temperature' => $this->temperature->value,
                'tonality' => $this->tonality->name,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
