<?php

declare(strict_types=1);

use App\Models\Organisation;
use App\Models\User;
use App\Models\UserProfile;

function buildUser(array $userState = []): User
{
    $org = Organisation::factory()->create();
    $user = User::factory()->create($userState);
    UserProfile::factory()->create([
        'user_id' => $user->id,
        'organisation_id' => $org->id,
    ]);

    return $user->fresh();
}

test('test_cli_auth_via_api_key_resolves_user', function (): void
{
    $user = buildUser();

    $response = $this->withHeader('Authorization', 'Bearer '.$user->api_key)
        ->getJson('/v1/users/me');

    $response->assertStatus(200);
    expect($response->json('id'))->toBe($user->id);
    expect($response->json('api_key'))->toBe($user->api_key);
});

test('test_web_auth_via_sanctum_token_resolves_user', function (): void
{
    $user = buildUser();

    $login = $this->postJson('/v1/auth/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $token = $login->json('access_token');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/v1/users/me');

    $response->assertStatus(200);
    expect($response->json('id'))->toBe($user->id);
});

test('test_inactive_user_api_key_returns_403', function (): void
{
    $user = buildUser(['is_active' => false]);

    $response = $this->withHeader('Authorization', 'Bearer '.$user->api_key)
        ->getJson('/v1/users/me');

    $response->assertStatus(403);
    expect($response->json('code'))->toBe('inactive_user');
});
