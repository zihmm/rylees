<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Project\Models\Project;
use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use App\Modules\ReleaseHistory\Models\ReleaseNote;

/*
 * Cross-cutting API contract guarantees.
 *
 * Covers AC-API-01 (routing, JSON content type, ISO 8601 UTC timestamps, UUID
 * primary keys) and AC-API-02 (soft-deleted records never appear in responses).
 */

const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

test('test_successful_response_carries_json_content_type', function (): void
{
    // AC-API-01: every response carries Content-Type: application/json — assert
    // it on a successful (2xx) response, not only on the error path.
    $this->getJson('/v1/ref/industries')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json');
});

test('test_primary_keys_in_responses_are_uuids', function (): void
{
    // AC-API-01: all primary keys in responses are UUIDs.
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/v1/customers/{$customer->id}");

    $response->assertOk();
    expect($response->json('id'))->toMatch(UUID_PATTERN);
    expect($response->json('organisation.id'))->toMatch(UUID_PATTERN);
});

test('test_response_timestamps_are_iso8601_utc', function (): void
{
    // AC-API-01: all timestamps in responses are ISO 8601 UTC. The public
    // release-history endpoint emits an explicit Zulu timestamp.
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    $history = ReleaseHistory::factory()->create(['project_id' => $project->id]);
    ReleaseNote::factory()->create([
        'release_history_id' => $history->id,
        'author_id' => $user->id,
    ]);

    $response = $this->getJson("/v1/public/release-history/{$customer->organisation->slug}/{$project->key}");

    $response->assertOk();
    // Strict Zulu (UTC) ISO 8601: 2026-06-05T12:00:00Z
    expect($response->json('items.0.publishedAt'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');

    // created_at fields elsewhere are also ISO 8601 in UTC (Z or +00:00),
    // optionally with a fractional-second component.
    $detail = $this->actingAs($user, 'sanctum')->getJson('/v1/customers');
    expect($detail->json('data.0.created_at'))
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|\+00:00)$/');
});

test('test_soft_deleted_customer_is_excluded_from_responses', function (): void
{
    // AC-API-02: soft-deleted records do not appear in any API response.
    $user = User::factory()->create();
    $visible = Customer::factory()->create(['user_id' => $user->id]);
    $deleted = Customer::factory()->create(['user_id' => $user->id]);
    $deleted->delete();

    $list = $this->actingAs($user, 'sanctum')->getJson('/v1/customers');

    $list->assertOk();
    expect($list->json('meta.total'))->toBe(1);
    expect(collect($list->json('data'))->pluck('id')->all())
        ->toContain($visible->id)
        ->not->toContain($deleted->id);

    // The soft-deleted customer is also unreachable on the detail route.
    $this->actingAs($user, 'sanctum')
        ->getJson("/v1/customers/{$deleted->id}")
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

test('test_soft_deleted_contact_is_excluded_from_customer_detail', function (): void
{
    // AC-API-02: soft-deleted child records are excluded too.
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $contact = App\Modules\Customer\Models\CustomerContact::factory()->create(['customer_id' => $customer->id]);
    $gone = App\Modules\Customer\Models\CustomerContact::factory()->create(['customer_id' => $customer->id]);
    $gone->delete();

    $response = $this->actingAs($user, 'sanctum')->getJson("/v1/customers/{$customer->id}");

    $response->assertOk();
    expect(collect($response->json('contacts'))->pluck('id')->all())
        ->toContain($contact->id)
        ->not->toContain($gone->id);
});
