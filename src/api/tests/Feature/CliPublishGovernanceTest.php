<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Project\Models\Project;
use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use App\Modules\ReleaseHistory\Models\ReleaseNote;

/*
 * CLI publish governance — AC-API-10, AC-API-15, AC-API-17.
 *
 * Publication is only ever the result of an explicit, authenticated developer
 * action carrying a human-reviewed body (ADR-002 / ADR-004). The API persists
 * the body verbatim and performs no generation of its own (ADR-001 / ADR-002).
 */

function publishSetup(): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    ReleaseHistory::factory()->create(['project_id' => $project->id]);

    return [$user, $project];
}

function publishPayload(array $overrides = []): array
{
    return array_replace([
        'startRef' => 'abc123',
        'endRef' => 'def456',
        'type' => 'commits',
        'body' => 'Human-reviewed release body.',
        'versionBump' => 'minor',
    ], $overrides);
}

test('test_publish_persists_the_body_verbatim', function (): void
{
    // AC-API-15 / AC-API-10: the API stores the caller-supplied body unchanged;
    // it neither generates nor rewrites it.
    [$user, $project] = publishSetup();

    $body = "Line one.\nLine two with ümlauts & symbols <>.";

    $response = $this->withToken($user->api_key)
        ->postJson("/v1/projects/{$project->token}/release-history", publishPayload(['body' => $body]));

    $response->assertStatus(201);

    $note = ReleaseNote::query()->findOrFail($response->json('id'));
    expect($note->body)->toBe($body);
});

test('test_publish_requires_a_body', function (): void
{
    // AC-API-17: a release note only exists when the developer supplies a
    // (reviewed) body — there is no unattended generate-and-publish path.
    [$user, $project] = publishSetup();

    $payload = publishPayload();
    unset($payload['body']);

    $response = $this->withToken($user->api_key)
        ->postJson("/v1/projects/{$project->token}/release-history", $payload);

    $response->assertStatus(422);
    expect($response->json('code'))->toBe('validation_error');
    expect($response->json('errors'))->toHaveKey('body');
});

test('test_publish_requires_authentication', function (): void
{
    // AC-API-17: publication is gated behind authentication; an anonymous
    // request is rejected and creates nothing.
    [$user, $project] = publishSetup();

    $this->postJson("/v1/projects/{$project->token}/release-history", publishPayload())
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');

    expect(ReleaseNote::query()->count())->toBe(0);
});

test('test_publish_to_another_developers_project_returns_403_and_creates_nothing', function (): void
{
    // AC-API-17 / AC-API-06: only the owning developer may publish to a project.
    [$owner, $project] = publishSetup();
    $intruder = User::factory()->create();

    $response = $this->withToken($intruder->api_key)
        ->postJson("/v1/projects/{$project->token}/release-history", publishPayload());

    $response->assertStatus(403);
    expect($response->json('code'))->toBe('forbidden');
    expect(ReleaseNote::query()->count())->toBe(0);
});
