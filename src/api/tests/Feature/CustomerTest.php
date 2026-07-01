<?php

declare(strict_types=1);

use App\Models\IndustryType;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerContact;
use App\Modules\Project\Models\Project;
use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use App\Modules\ReleaseHistory\Models\ReleaseNote;
use Illuminate\Support\Str;

test('test_list_customers_returns_paginated_response_with_projects_count', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    Project::factory()->count(2)->create(['customer_id' => $customer->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/v1/customers');

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.projects_count', 2);
    $response->assertJsonStructure([
        'data' => [['id', 'description', 'projects_count', 'organisation', 'main_contact', 'industry', 'created_at', 'updated_at']],
        'meta' => ['current_page', 'last_page', 'total'],
    ]);
});

test('test_create_customer_creates_organisation_and_customer', function (): void
{
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/v1/customers', [
        'organisation' => ['name' => 'Acme Ltd.'],
        'description' => 'A test customer',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['id', 'organisation' => ['id', 'name', 'slug'], 'created_at']);
    $this->assertDatabaseHas('organisations', ['name' => 'Acme Ltd.']);
    $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'description' => 'A test customer']);
});

test('test_create_customer_with_contact_sets_main_contact_id', function (): void
{
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/v1/customers', [
        'organisation' => ['name' => 'Acme Ltd.'],
        'main_contact' => [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@acme.com',
        ],
    ]);

    $response->assertStatus(201);

    $customer = Customer::query()->findOrFail($response->json('id'));
    $contact = CustomerContact::query()->where('customer_id', $customer->id)->firstOrFail();

    expect($customer->main_contact_id)->toBe($contact->id);
});

test('test_get_customer_returns_contacts_and_projects', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    CustomerContact::factory()->create(['customer_id' => $customer->id]);
    Project::factory()->create(['customer_id' => $customer->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/v1/customers/{$customer->id}");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'id', 'description', 'organisation', 'industry',
        'contacts' => [['id', 'firstname', 'lastname', 'email']],
        'main_contact',
        'projects' => [['id', 'name', 'key']],
    ]);
    expect($response->json('contacts'))->toHaveCount(1);
    expect($response->json('projects'))->toHaveCount(1);
});

test('test_cannot_access_other_users_customer_returns_404', function (): void
{
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $userB->id]);

    $this->actingAs($userA, 'sanctum')
        ->getJson("/v1/customers/{$customer->id}")
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');

    $this->actingAs($userA, 'sanctum')
        ->patchJson("/v1/customers/{$customer->id}", ['description' => 'hacked'])
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

test('test_patch_customer_does_not_accept_contacts_field', function (): void
{
    $user = User::factory()->create();
    $industry = IndustryType::create(['id' => (string) Str::uuid(), 'name' => 'Architecture']);
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')->patchJson("/v1/customers/{$customer->id}", [
        'description' => 'Updated description',
        'industry_id' => $industry->id,
        'contacts' => [['firstname' => 'X', 'lastname' => 'Y', 'email' => 'x@y.com']],
        'main_contact' => ['firstname' => 'X', 'lastname' => 'Y', 'email' => 'x@y.com'],
    ]);

    $response->assertStatus(200);

    expect(CustomerContact::query()->where('customer_id', $customer->id)->count())->toBe(0);
    expect($customer->fresh()->main_contact_id)->toBeNull();
    expect($customer->fresh()->description)->toBe('Updated description');
});

test('test_delete_customer_cascades_to_contacts_projects_and_release_notes', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $contact = CustomerContact::factory()->create(['customer_id' => $customer->id]);
    $customer->main_contact_id = $contact->id;
    $customer->save();

    $project = Project::factory()->create(['customer_id' => $customer->id]);
    $history = ReleaseHistory::factory()->create(['project_id' => $project->id]);
    $note = ReleaseNote::factory()->create([
        'release_history_id' => $history->id,
        'author_id' => $user->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')->deleteJson("/v1/customers/{$customer->id}");

    $response->assertStatus(204);

    $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    $this->assertSoftDeleted('customer_contacts', ['id' => $contact->id]);
    $this->assertSoftDeleted('projects', ['id' => $project->id]);
    $this->assertSoftDeleted('release_histories', ['id' => $history->id]);
    $this->assertSoftDeleted('release_notes', ['id' => $note->id]);
});

test('test_delete_customer_removes_it_from_listing', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/v1/customers/{$customer->id}")
        ->assertStatus(204);

    $this->actingAs($user, 'sanctum')
        ->getJson("/v1/customers/{$customer->id}")
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

test('test_cannot_delete_other_users_customer', function (): void
{
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $userB->id]);

    $this->actingAs($userA, 'sanctum')
        ->deleteJson("/v1/customers/{$customer->id}")
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'deleted_at' => null]);
});
