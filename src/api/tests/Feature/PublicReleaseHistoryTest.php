<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AI\Services\TranslationService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Project\Models\Project;
use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use App\Modules\ReleaseHistory\Models\ReleaseNote;

function makePublicReleaseHistorySetup(): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    $history = ReleaseHistory::factory()->create(['project_id' => $project->id]);

    $organisation = $customer->organisation;

    ReleaseNote::factory()->create([
        'release_history_id' => $history->id,
        'author_id' => $user->id,
        'body' => 'Older release.',
        'version_major' => 1,
        'version_minor' => 0,
        'version_patch' => 0,
        'created_at' => now()->subMinutes(10),
    ]);

    $newest = ReleaseNote::factory()->create([
        'release_history_id' => $history->id,
        'author_id' => $user->id,
        'body' => 'Newest release.',
        'version_major' => 1,
        'version_minor' => 1,
        'version_patch' => 0,
        'created_at' => now(),
    ]);

    return [$organisation, $customer, $project, $history, $newest];
}

test('test_public_endpoint_returns_notes_without_auth', function (): void
{
    [$organisation, $customer, $project, $history, $newest] = makePublicReleaseHistorySetup();

    $response = $this->getJson("/v1/public/release-history/{$organisation->slug}/{$project->key}");

    $response->assertStatus(200);
    expect($response->json('project.key'))->toBe($project->key);
    expect($response->json('items'))->not->toBeEmpty();
    expect($response->json('items.0.version'))->toBe('1.1.0');
    expect($response->json('items.0.body'))->toBe('Newest release.');
});

test('test_public_endpoint_returns_404_for_unknown_slug', function (): void
{
    [$organisation, $customer, $project, $history, $newest] = makePublicReleaseHistorySetup();

    $response = $this->getJson("/v1/public/release-history/unknown-slug/{$project->key}");

    $response->assertStatus(404);
    expect($response->json('code'))->toBe('not_found');
});

test('test_translate_with_invalid_language_returns_422', function (): void
{
    [$organisation, $customer, $project, $history, $newest] = makePublicReleaseHistorySetup();

    $response = $this->getJson("/v1/public/release-history/{$organisation->slug}/{$project->key}/translate?language=xx");

    $response->assertStatus(422);
    expect($response->json('code'))->toBe('validation_error');
});

test('test_translate_returns_translated_bodies', function (): void
{
    [$organisation, $customer, $project, $history, $newest] = makePublicReleaseHistorySetup();

    // TranslationService is final; bind an anonymous test double that records
    // the arguments it receives and returns translated bodies.
    $fake = new class
    {
        /** @var array<string, mixed> */
        public array $received = [];

        public function translate(array $notes, string $language): array
        {
            $this->received = ['notes' => $notes, 'language' => $language];

            return array_map(
                fn (array $note): array => ['id' => $note['id'], 'body' => 'FR: '.$note['body']],
                $notes
            );
        }
    };

    $this->app->instance(TranslationService::class, $fake);

    $response = $this->getJson("/v1/public/release-history/{$organisation->slug}/{$project->key}/translate?language=fr");

    $response->assertStatus(200);
    expect($response->json('language'))->toBe('fr');

    // Service was handed the original German bodies (newest first) and the language.
    expect($fake->received['language'])->toBe('fr');
    expect($fake->received['notes'])->toHaveCount(2);
    expect($fake->received['notes'][0]['body'])->toBe('Newest release.');

    // Response items carry the reconstructed version + the translated body.
    expect($response->json('items.0.version'))->toBe('1.1.0');
    expect($response->json('items.0.body'))->toBe('FR: Newest release.');
    expect($response->json('items.1.version'))->toBe('1.0.0');
    expect($response->json('items.1.body'))->toBe('FR: Older release.');
});
