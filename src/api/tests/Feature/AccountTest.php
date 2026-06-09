<?php

declare(strict_types=1);

use App\Models\Organisation;
use App\Models\User;
use App\Models\UserProfile;
use App\Modules\Auth\Mail\AccountActivationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function registrationPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'username' => 'jane@example.com',
        'password' => 'super-secret-password',
        'profile' => [
            'firstname' => 'Jane',
            'lastname' => 'Doe',
        ],
        'organisation' => [
            'name' => 'Doe Digital GmbH',
            'street' => 'Main Street 1',
            'postcode' => '8000',
            'city' => 'Zurich',
            'website' => 'https://doe.example',
            'email' => 'info@doe.example',
        ],
    ], $overrides);
}

test('test_register_creates_user_profile_and_organisation', function (): void
{
    Mail::fake();

    $response = $this->postJson('/v1/users/register', registrationPayload());

    $response->assertStatus(201);
    $this->assertDatabaseHas('users', ['username' => 'jane@example.com']);
    $this->assertDatabaseHas('user_profiles', ['firstname' => 'Jane', 'lastname' => 'Doe']);
    $this->assertDatabaseHas('organisations', ['name' => 'Doe Digital GmbH']);

    expect($response->json('organisation.slug'))->toBe('doe-digital-gmbh');
});

test('test_register_sets_is_active_false', function (): void
{
    Mail::fake();

    $response = $this->postJson('/v1/users/register', registrationPayload());

    $response->assertStatus(201);
    expect($response->json('user.is_active'))->toBeFalse();
    expect($response->json('user.activated_at'))->toBeNull();

    $user = User::where('username', 'jane@example.com')->first();
    expect($user->is_active)->toBeFalse();
    expect($user->activation_token)->not->toBeNull();
});

test('test_register_sends_activation_email', function (): void
{
    Mail::fake();

    $this->postJson('/v1/users/register', registrationPayload())->assertStatus(201);

    Mail::assertSent(AccountActivationMail::class);
});

test('test_activate_with_valid_token_activates_user', function (): void
{
    $token = Str::random(64);
    $org = Organisation::factory()->create();
    $user = User::factory()->inactive()->create(['activation_token' => $token]);
    UserProfile::factory()->create(['user_id' => $user->id, 'organisation_id' => $org->id]);

    $response = $this->getJson('/v1/users/activate?token='.$token);

    $response->assertStatus(200);
    $user->refresh();
    expect($user->is_active)->toBeTrue();
    expect($user->activation_token)->toBeNull();
    expect($user->activated_at)->not->toBeNull();
});

test('test_activate_with_invalid_token_returns_404', function (): void
{
    $response = $this->getJson('/v1/users/activate?token='.Str::random(64));

    $response->assertStatus(404);
    expect($response->json('code'))->toBe('not_found');
});

test('test_activate_with_already_used_token_returns_404', function (): void
{
    $token = Str::random(64);
    $org = Organisation::factory()->create();
    $user = User::factory()->inactive()->create(['activation_token' => $token]);
    UserProfile::factory()->create(['user_id' => $user->id, 'organisation_id' => $org->id]);

    $this->getJson('/v1/users/activate?token='.$token)->assertStatus(200);

    $response = $this->getJson('/v1/users/activate?token='.$token);

    $response->assertStatus(404);
    expect($response->json('code'))->toBe('not_found');
});
