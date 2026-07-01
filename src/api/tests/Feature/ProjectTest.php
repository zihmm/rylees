<?php

declare(strict_types=1);

use App\Models\LlmTemperatureType;
use App\Models\LlmTonalityType;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Project\Models\Project;
use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use App\Modules\ReleaseHistory\Models\ReleaseNote;
use Illuminate\Support\Str;

test('test_create_project_generates_key_and_token_and_creates_release_history', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $tonality = LlmTonalityType::create(['id' => (string) Str::uuid(), 'name' => 'professional']);
    $temperature = LlmTemperatureType::create(['id' => (string) Str::uuid(), 'name' => 'balanced', 'value' => 0.5]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/v1/customers/{$customer->id}/projects", [
        'name' => 'Member Portal',
        'description' => 'A test project',
        'llm_tonality_id' => $tonality->id,
        'llm_temperature_id' => $temperature->id,
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['id', 'name', 'key', 'token', 'created_at']);

    expect($response->json('key'))->toBe('member-portal');
    expect($response->json('token'))->toHaveLength(64);

    $this->assertDatabaseHas('release_histories', ['project_id' => $response->json('id')]);
});

test('test_create_and_update_persist_project_language', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $tonality = LlmTonalityType::create(['id' => (string) Str::uuid(), 'name' => 'professional']);
    $temperature = LlmTemperatureType::create(['id' => (string) Str::uuid(), 'name' => 'balanced', 'value' => 0.5]);

    $create = $this->actingAs($user, 'sanctum')->postJson("/v1/customers/{$customer->id}/projects", [
        'name' => 'Localized Project',
        'language' => 'de',
        'llm_tonality_id' => $tonality->id,
        'llm_temperature_id' => $temperature->id,
    ]);

    $create->assertStatus(201);
    $this->assertDatabaseHas('projects', ['id' => $create->json('id'), 'language' => 'de']);

    $update = $this->actingAs($user, 'sanctum')
        ->patchJson("/v1/customers/{$customer->id}/projects/{$create->json('id')}", ['language' => 'fr']);

    $update->assertStatus(200);
    expect($update->json('language'))->toBe('fr');
    $this->assertDatabaseHas('projects', ['id' => $create->json('id'), 'language' => 'fr']);
});

test('test_invalid_project_language_is_rejected', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/v1/customers/{$customer->id}/projects/{$project->id}", ['language' => 'xx'])
        ->assertStatus(422);
});

test('test_project_token_appears_in_single_response_not_list', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);

    $detail = $this->actingAs($user, 'sanctum')
        ->getJson("/v1/customers/{$customer->id}/projects/{$project->id}");

    $detail->assertStatus(200);
    expect($detail->json('token'))->toBe($project->token);

    $customerList = $this->actingAs($user, 'sanctum')->getJson('/v1/customers');

    $customerList->assertStatus(200);
    expect($customerList->getContent())->not->toContain($project->token);
    expect($customerList->getContent())->not->toContain('"token"');
});

test('test_patch_project_cannot_change_token_or_key', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);

    $originalToken = $project->token;
    $originalKey = $project->key;

    $response = $this->actingAs($user, 'sanctum')->patchJson("/v1/customers/{$customer->id}/projects/{$project->id}", [
        'token' => 'hacked-token',
        'key' => 'hacked-key',
        'description' => 'Updated description',
    ]);

    $response->assertStatus(200);

    $fresh = $project->fresh();
    expect($fresh->token)->toBe($originalToken);
    expect($fresh->key)->toBe($originalKey);
    expect($fresh->description)->toBe('Updated description');
});

test('test_project_detail_includes_customer_organisation_slug', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/v1/customers/{$customer->id}/projects/{$project->id}");

    $response->assertStatus(200);
    expect($response->json('customer.organisation_slug'))->toBe($customer->organisation->slug);
});

test('test_list_all_projects_returns_overview_for_every_owned_customer', function (): void
{
    $user = User::factory()->create();
    $customerA = Customer::factory()->create(['user_id' => $user->id]);
    $customerB = Customer::factory()->create(['user_id' => $user->id]);
    Project::factory()->create(['customer_id' => $customerA->id, 'name' => 'Alpha']);
    Project::factory()->create(['customer_id' => $customerB->id, 'name' => 'Beta']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/v1/projects');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [['id', 'name', 'customer_id', 'customer_name', 'description', 'version', 'updated_at']],
    ]);
    expect($response->json('data'))->toHaveCount(2);
    expect(collect($response->json('data'))->pluck('name')->all())
        ->toContain('Alpha', 'Beta');
});

test('test_list_all_projects_excludes_other_users_projects', function (): void
{
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = Customer::factory()->create(['user_id' => $user->id]);
    $theirs = Customer::factory()->create(['user_id' => $other->id]);
    Project::factory()->create(['customer_id' => $mine->id, 'name' => 'Mine']);
    Project::factory()->create(['customer_id' => $theirs->id, 'name' => 'Theirs']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/v1/projects');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Mine');
});

test('test_list_all_projects_excludes_token', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/v1/projects');

    $response->assertStatus(200);
    expect($response->getContent())->not->toContain('"token"');
    expect($response->getContent())->not->toContain($project->token);
});

test('test_list_all_projects_reports_customer_name_and_latest_version', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    $history = ReleaseHistory::factory()->create(['project_id' => $project->id]);

    // Older note first, then the current version — latest is resolved by created_at.
    ReleaseNote::factory()->create([
        'release_history_id' => $history->id,
        'author_id' => $user->id,
        'version_major' => 1,
        'version_minor' => 0,
        'version_patch' => 0,
        'created_at' => now()->subDay(),
    ]);
    ReleaseNote::factory()->create([
        'release_history_id' => $history->id,
        'author_id' => $user->id,
        'version_major' => 1,
        'version_minor' => 2,
        'version_patch' => 3,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/v1/projects');

    $response->assertStatus(200);
    expect($response->json('data.0.id'))->toBe($project->id);
    expect($response->json('data.0.customer_id'))->toBe($customer->id);
    expect($response->json('data.0.customer_name'))->toBe($customer->organisation->name);
    expect($response->json('data.0.version'))->toBe('1.2.3');
});

test('test_list_all_projects_includes_key_and_organisation_slug_for_history_link', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/v1/projects');

    $response->assertStatus(200);
    expect($response->json('data.0.key'))->toBe($project->key);
    expect($response->json('data.0.organisation_slug'))->toBe($customer->organisation->slug);
});

test('test_list_all_projects_version_is_null_without_release_notes', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    ReleaseHistory::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/v1/projects');

    $response->assertStatus(200);
    expect($response->json('data.0.version'))->toBeNull();
});

test('test_list_all_projects_truncates_description_to_180_chars', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    Project::factory()->create([
        'customer_id' => $customer->id,
        'description' => str_repeat('a', 300),
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/v1/projects');

    $response->assertStatus(200);
    // Str::limit keeps 180 chars and appends an ellipsis.
    expect($response->json('data.0.description'))->toBe(str_repeat('a', 180).'...');
});

test('test_list_all_projects_requires_authentication', function (): void
{
    $this->getJson('/v1/projects')->assertStatus(401);
});

test('test_delete_project_cascades_to_release_history_and_notes', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    $history = ReleaseHistory::factory()->create(['project_id' => $project->id]);
    $note = ReleaseNote::factory()->create([
        'release_history_id' => $history->id,
        'author_id' => $user->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson("/v1/customers/{$customer->id}/projects/{$project->id}");

    $response->assertStatus(204);
    $this->assertSoftDeleted('projects', ['id' => $project->id]);
    $this->assertSoftDeleted('release_histories', ['id' => $history->id]);
    $this->assertSoftDeleted('release_notes', ['id' => $note->id]);
});

test('test_cannot_delete_project_of_other_users_customer', function (): void
{
    $user = User::factory()->create();
    $other = User::factory()->create();
    $theirs = Customer::factory()->create(['user_id' => $other->id]);
    $project = Project::factory()->create(['customer_id' => $theirs->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson("/v1/customers/{$theirs->id}/projects/{$project->id}");

    $response->assertStatus(404)->assertJsonPath('code', 'not_found');
    $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
});

test('test_delete_project_requires_authentication', function (): void
{
    $customer = Customer::factory()->create();
    $project = Project::factory()->create(['customer_id' => $customer->id]);

    $this->deleteJson("/v1/customers/{$customer->id}/projects/{$project->id}")
        ->assertStatus(401);
});
