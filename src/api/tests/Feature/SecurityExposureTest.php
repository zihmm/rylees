<?php

declare(strict_types=1);

use App\Models\Organisation;
use App\Models\User;
use App\Models\UserProfile;
use App\Modules\Customer\Models\Customer;
use App\Modules\Project\Models\Project;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/*
 * Security field exposure guarantees — AC-API-09.
 *
 * `api_key` is exposed only by GET /users/me; `projects.token` never appears in
 * list responses; passwords are stored as bcrypt hashes and never returned.
 */

function exposureUser(array $state = []): User
{
    $org = Organisation::factory()->create();
    $user = User::factory()->create($state);
    UserProfile::factory()->create([
        'user_id' => $user->id,
        'organisation_id' => $org->id,
    ]);

    return $user->fresh();
}

test('test_api_key_is_absent_from_login_response', function (): void
{
    $user = exposureUser();

    $response = $this->postJson('/v1/auth/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $response->assertOk();
    expect($response->json('user'))->not->toHaveKey('api_key');
    expect($response->getContent())->not->toContain($user->api_key);
});

test('test_api_key_is_absent_from_customer_list_response', function (): void
{
    $user = exposureUser();
    Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/v1/customers');

    $response->assertOk();
    expect($response->getContent())->not->toContain($user->api_key);
});

test('test_api_key_is_present_in_users_me_only', function (): void
{
    $user = exposureUser();

    // Present in the dedicated self endpoint…
    $this->actingAs($user, 'sanctum')
        ->getJson('/v1/users/me')
        ->assertOk()
        ->assertJsonPath('api_key', $user->api_key);
});

test('test_project_token_is_absent_from_customer_list_response', function (): void
{
    $user = exposureUser();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/v1/customers');

    $response->assertOk();
    expect($response->getContent())
        ->not->toContain($project->token)
        ->not->toContain('"token"');
});

test('test_password_is_stored_as_bcrypt_and_never_returned', function (): void
{
    Mail::fake();

    $response = $this->postJson('/v1/users/register', [
        'username' => 'secure@example.com',
        'password' => 'super-secret-password',
        'profile' => ['firstname' => 'Sec', 'lastname' => 'Ure'],
        'organisation' => ['name' => 'Secure GmbH'],
    ]);

    $response->assertStatus(201);

    $user = User::where('username', 'secure@example.com')->firstOrFail();

    // Stored as a bcrypt hash, not plaintext.
    expect($user->password)->toStartWith('$2y$');
    expect($user->password)->not->toBe('super-secret-password');
    expect(Hash::check('super-secret-password', $user->password))->toBeTrue();

    // The plaintext password never appears in the response body.
    expect($response->getContent())->not->toContain('super-secret-password');
    expect($response->json('user'))->not->toHaveKey('password');
});
