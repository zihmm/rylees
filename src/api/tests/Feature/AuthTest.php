<?php

declare(strict_types=1);

use App\Models\Organisation;
use App\Models\User;
use App\Models\UserProfile;

function makeUser(array $userState = []): User
{
    $org = Organisation::factory()->create();
    $user = User::factory()->create($userState);
    UserProfile::factory()->create([
        'user_id' => $user->id,
        'organisation_id' => $org->id,
    ]);

    return $user->fresh();
}

test('test_login_with_valid_credentials_returns_token', function (): void
{
    $user = makeUser();

    $response = $this->postJson('/v1/auth/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $response->assertStatus(200);
    expect($response->json('access_token'))->not->toBeEmpty();
    expect($response->json('expires_in'))->toBe(3600);
    expect($response->json('token_type'))->toBe('Bearer');
    expect($response->json('user.profile'))->not->toBeNull();
    expect($response->json('user.organisation'))->not->toBeNull();
});

test('test_login_with_inactive_user_returns_403_inactive_user', function (): void
{
    $user = makeUser(['is_active' => false]);

    $response = $this->postJson('/v1/auth/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $response->assertStatus(403);
    expect($response->json('code'))->toBe('inactive_user');
});

test('test_login_with_wrong_password_returns_401_unauthenticated', function (): void
{
    $user = makeUser();

    $response = $this->postJson('/v1/auth/login', [
        'username' => $user->username,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
    expect($response->json('code'))->toBe('unauthenticated');
});

test('test_login_with_unknown_username_returns_401_unauthenticated', function (): void
{
    $response = $this->postJson('/v1/auth/login', [
        'username' => 'nobody@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(401);
    expect($response->json('code'))->toBe('unauthenticated');
});

test('test_logout_revokes_token', function (): void
{
    $user = makeUser();

    $login = $this->postJson('/v1/auth/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $token = $login->json('access_token');

    expect($user->tokens()->count())->toBe(1);

    $logout = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/v1/auth/logout');

    $logout->assertStatus(204);
    expect($user->fresh()->tokens()->count())->toBe(0);

    // Each real HTTP request resolves auth guards from scratch; the test
    // harness otherwise caches the previously-resolved guard user across
    // sub-requests, so forget the guards to simulate a fresh request.
    $this->app['auth']->forgetGuards();

    $after = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/v1/users/me');

    $after->assertStatus(401);
});
