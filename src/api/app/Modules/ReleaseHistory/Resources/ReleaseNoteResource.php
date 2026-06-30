<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Resources;

use App\Modules\ReleaseHistory\Models\ReleaseNote;
use App\Modules\ReleaseHistory\Services\MarkdownRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReleaseNote
 */
final class ReleaseNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => "{$this->version_major}.{$this->version_minor}.{$this->version_patch}",
            'body' => MarkdownRenderer::toHtml($this->body),
            'publishedAt' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
