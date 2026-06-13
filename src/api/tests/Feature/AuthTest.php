<?php

declare(strict_types=1);

use App\Models\Organisation;
use App\Models\User;
use App\Models\UserProfile;
use App\Modules\Auth\Mail\PasswordResetMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

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

test('test_forgot_password_with_known_email_sends_reset_mail_and_sets_token', function (): void
{
    Mail::fake();

    $user = makeUser();

    $response = $this->postJson('/v1/auth/forgot-password', [
        'username' => $user->username,
    ]);

    $response->assertStatus(200);

    $user->refresh();
    expect($user->password_reset_token)->not->toBeNull();
    expect($user->password_reset_expires_at)->not->toBeNull();

    Mail::assertSent(PasswordResetMail::class, fn (PasswordResetMail $mail): bool => $mail->user->is($user));
});

test('test_forgot_password_with_unknown_email_returns_200_and_sends_nothing', function (): void
{
    Mail::fake();

    $response = $this->postJson('/v1/auth/forgot-password', [
        'username' => 'nobody@example.com',
    ]);

    $response->assertStatus(200);

    Mail::assertNothingSent();
});

test('test_forgot_password_requires_valid_email', function (): void
{
    $response = $this->postJson('/v1/auth/forgot-password', [
        'username' => 'not-an-email',
    ]);

    $response->assertStatus(422);
    expect($response->json('code'))->toBe('validation_error');
});

test('test_reset_password_with_valid_token_changes_password_and_clears_token', function (): void
{
    $user = makeUser();
    $user->forceFill([
        'password_reset_token' => 'valid-reset-token',
        'password_reset_expires_at' => now()->addMinutes(60),
    ])->save();

    $response = $this->postJson('/v1/auth/reset-password', [
        'token' => 'valid-reset-token',
        'password' => 'new-secure-password',
    ]);

    $response->assertStatus(200);

    $user->refresh();
    expect(Hash::check('new-secure-password', $user->password))->toBeTrue();
    expect($user->password_reset_token)->toBeNull();
    expect($user->password_reset_expires_at)->toBeNull();
});

test('test_reset_password_revokes_existing_tokens', function (): void
{
    $user = makeUser();
    $user->createToken('web');
    $user->forceFill([
        'password_reset_token' => 'valid-reset-token',
        'password_reset_expires_at' => now()->addMinutes(60),
    ])->save();

    expect($user->tokens()->count())->toBe(1);

    $this->postJson('/v1/auth/reset-password', [
        'token' => 'valid-reset-token',
        'password' => 'new-secure-password',
    ])->assertStatus(200);

    expect($user->fresh()->tokens()->count())->toBe(0);
});

test('test_reset_password_with_invalid_token_returns_404', function (): void
{
    makeUser();

    $response = $this->postJson('/v1/auth/reset-password', [
        'token' => 'does-not-exist',
        'password' => 'new-secure-password',
    ]);

    $response->assertStatus(404);
    expect($response->json('code'))->toBe('not_found');
});

test('test_reset_password_with_expired_token_returns_404', function (): void
{
    $user = makeUser();
    $user->forceFill([
        'password_reset_token' => 'expired-reset-token',
        'password_reset_expires_at' => now()->subMinute(),
    ])->save();

    $response = $this->postJson('/v1/auth/reset-password', [
        'token' => 'expired-reset-token',
        'password' => 'new-secure-password',
    ]);

    $response->assertStatus(404);
    expect($response->json('code'))->toBe('not_found');
});

test('test_reset_password_enforces_minimum_length', function (): void
{
    $response = $this->postJson('/v1/auth/reset-password', [
        'token' => 'whatever',
        'password' => 'short',
    ]);

    $response->assertStatus(422);
    expect($response->json('code'))->toBe('validation_error');
});
