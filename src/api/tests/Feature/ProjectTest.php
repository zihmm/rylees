<?php

declare(strict_types=1);

use App\Models\LlmTemperatureType;
use App\Models\LlmTonalityType;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Project\Models\Project;
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
