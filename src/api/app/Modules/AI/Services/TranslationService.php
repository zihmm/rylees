<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use OpenAI;
use OpenAI\Client;

final class TranslationService
{
    private Client $client;

    public function __construct()
    {
        $this->client = OpenAI::client((string) config('services.openai.key'));
    }

    /**
     * Translate release-note bodies from German into the target language.
     *
     * @param  array<int, array{id: string, body: string}>  $notes
     * @return array<int, array{id: string, body: string}>
     */
    public function translate(array $notes, string $targetLanguage): array
    {
        $systemPrompt = <<<PROMPT
You are translating release notes from German into {$targetLanguage}.
The audience is non-technical.
Return a JSON array where each object has exactly two keys: "id" and "body".
Translate only the "body" value. Do not alter IDs, version numbers, or dates.
Return only the JSON array, nothing else.
PROMPT;

        $userMessage = json_encode($notes, JSON_UNESCAPED_UNICODE);

        $response = $this->client->chat()->create([
            'model' => 'GPT-5.4',
            'temperature' => 0.3,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        $content = $response->choices[0]->message->content;

        return json_decode($content, true);
    }
}
