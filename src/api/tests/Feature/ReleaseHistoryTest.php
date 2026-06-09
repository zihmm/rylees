<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Project\Models\Project;
use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use App\Modules\ReleaseHistory\Models\ReleaseNote;

function makeReleaseHistorySetup(): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    $history = ReleaseHistory::factory()->create(['project_id' => $project->id]);

    return [$user, $customer, $project, $history];
}

test('test_publish_with_no_prior_notes_creates_version_0_1_0', function (): void
{
    [$user, $customer, $project, $history] = makeReleaseHistorySetup();

    $response = $this->withToken($user->api_key)
        ->postJson("/v1/projects/{$project->token}/release-history", [
            'startRef' => 'abc123',
            'endRef' => 'def456',
            'type' => 'commits',
            'body' => 'Initial release.',
            'versionBump' => 'minor',
        ]);

    $response->assertStatus(201);
    expect($response->json('status'))->toBe('published');
    expect($response->json('version'))->toBe('0.1.0');
});

test('test_publish_major_bump_resets_minor_and_patch', function (): void
{
    [$user, $customer, $project, $history] = makeReleaseHistorySetup();

    ReleaseNote::factory()->create([
        'release_history_id' => $history->id,
        'author_id' => $user->id,
        'version_major' => 1,
        'version_minor' => 2,
        'version_patch' => 3,
        'created_at' => now()->subMinute(),
    ]);

    $response = $this->withToken($user->api_key)
        ->postJson("/v1/projects/{$project->token}/release-history", [
            'startRef' => 'abc123',
            'endRef' => 'def456',
            'type' => 'commits',
            'body' => 'Major release.',
            'versionBump' => 'major',
        ]);

    $response->assertStatus(201);
    expect($response->json('version'))->toBe('2.0.0');
});

test('test_publish_minor_bump_resets_patch', function (): void
{
    [$user, $customer, $project, $history] = makeReleaseHistorySetup();

    ReleaseNote::factory()->create([
        'release_history_id' => $history->id,
        'author_id' => $user->id,
        'version_major' => 1,
        'version_minor' => 2,
        'version_patch' => 3,
        'created_at' => now()->subMinute(),
    ]);

    $response = $this->withToken($user->api_key)
        ->postJson("/v1/projects/{$project->token}/release-history", [
            'startRef' => 'abc123',
            'endRef' => 'def456',
            'type' => 'commits',
            'body' => 'Minor release.',
            'versionBump' => 'minor',
        ]);

    $response->assertStatus(201);
    expect($response->json('version'))->toBe('1.3.0');
});

test('test_publish_commits_type_populates_commithash_columns', function (): void
{
    [$user, $customer, $project, $history] = makeReleaseHistorySetup();

    $response = $this->withToken($user->api_key)
        ->postJson("/v1/projects/{$project->token}/release-history", [
            'startRef' => 'startcommit',
            'endRef' => 'endcommit',
            'type' => 'commits',
            'body' => 'Commit-based release.',
            'versionBump' => 'patch',
        ]);

    $response->assertStatus(201);

    $note = ReleaseNote::query()->findOrFail($response->json('id'));

    expect($note->commithash_start)->toBe('startcommit');
    expect($note->commithash_end)->toBe('endcommit');
    expect($note->tag_start)->toBeNull();
    expect($note->tag_end)->toBeNull();
});

test('test_publish_tag_type_populates_tag_columns', function (): void
{
    [$user, $customer, $project, $history] = makeReleaseHistorySetup();

    $response = $this->withToken($user->api_key)
        ->postJson("/v1/projects/{$project->token}/release-history", [
            'startRef' => 'v1.0.0',
            'endRef' => 'v1.1.0',
            'type' => 'tag',
            'body' => 'Tag-based release.',
            'versionBump' => 'patch',
        ]);

    $response->assertStatus(201);

    $note = ReleaseNote::query()->findOrFail($response->json('id'));

    expect($note->tag_start)->toBe('v1.0.0');
    expect($note->tag_end)->toBe('v1.1.0');
    expect($note->commithash_start)->toBeNull();
    expect($note->commithash_end)->toBeNull();
});
