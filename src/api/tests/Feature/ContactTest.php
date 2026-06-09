<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerContact;

test('test_create_contact_returns_201', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/v1/customers/{$customer->id}/contacts", [
        'firstname' => 'Jane',
        'lastname' => 'Smith',
        'email' => 'jane@acme.com',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['id', 'firstname', 'lastname', 'email']);
    $this->assertDatabaseHas('customer_contacts', [
        'customer_id' => $customer->id,
        'email' => 'jane@acme.com',
    ]);
});

test('test_delete_contact_sets_main_contact_id_to_null_if_it_was_main', function (): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $contact = CustomerContact::factory()->create(['customer_id' => $customer->id]);
    $customer->main_contact_id = $contact->id;
    $customer->save();

    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson("/v1/customers/{$customer->id}/contacts/{$contact->id}");

    $response->assertStatus(204);

    expect($customer->fresh()->main_contact_id)->toBeNull();
    $this->assertSoftDeleted('customer_contacts', ['id' => $contact->id]);
});

test('test_cannot_access_contact_of_other_users_customer', function (): void
{
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $userB->id]);
    $contact = CustomerContact::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($userA, 'sanctum')
        ->patchJson("/v1/customers/{$customer->id}/contacts/{$contact->id}", ['firstname' => 'Hacker'])
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');

    $this->actingAs($userA, 'sanctum')
        ->deleteJson("/v1/customers/{$customer->id}/contacts/{$contact->id}")
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});
