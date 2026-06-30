<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Services;

use Illuminate\Support\Str;

/**
 * Renders release-note markdown to HTML for display.
 *
 * Release-note bodies are authored/AI-generated free text, so the output is
 * sanitized for safe client-side rendering: any raw HTML in the source is
 * escaped (never interpreted) and unsafe link schemes (javascript:, etc.) are
 * stripped. The result therefore only contains the inline/block tags produced
 * by markdown syntax (strong, em, ul, a, code, ...), which the frontend renders
 * with v-html.
 */
final class MarkdownRenderer
{
    public static function toHtml(string $markdown): string
    {
        return mb_trim(Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]));
    }
}
