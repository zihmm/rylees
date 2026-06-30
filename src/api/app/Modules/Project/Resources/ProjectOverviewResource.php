<?php

declare(strict_types=1);

namespace App\Modules\Project\Resources;

use App\Modules\Project\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Condensed project row for the developer console overview list across all
 * customers. Deliberately omits `token` — it is exposed only in detail/create
 * responses, never in lists.
 *
 * @mixin Project
 */
final class ProjectOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestNote = $this->releaseHistory?->latestReleaseNote;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer->organisation->name,
            'organisation_slug' => $this->customer->organisation->slug,
            'description' => $this->description === null
                ? null
                : Str::limit($this->description, 180),
            'version' => $latestNote === null
                ? null
                : sprintf('%d.%d.%d', $latestNote->version_major, $latestNote->version_minor, $latestNote->version_patch),
            'updated_at' => $this->updated_at,
        ];
    }
}
