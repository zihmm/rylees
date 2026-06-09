<?php

declare(strict_types=1);

use App\Models\Organisation;
use App\Models\User;
use App\Models\UserProfile;
use App\Modules\Customer\Models\CustomerContact;
use App\Modules\Project\Models\Project;
use App\Modules\ReleaseHistory\Models\ReleaseNote;

it('creates users with uuid keys, api keys and hidden fields', function (): void
{
    $user = User::factory()->create();

    expect($user->id)->toBeString()->and(mb_strlen($user->id))->toBe(36);
    expect(mb_strlen($user->api_key))->toBe(64);
    expect($user->toArray())->not->toHaveKeys(['password', 'activation_token']);
});

it('generates a slug for organisations via HasSlug', function (): void
{
    $org = Organisation::factory()->create(['name' => 'Doe Digital GmbH']);

    expect($org->slug)->toBe('doe-digital-gmbh');
});

it('wires the user profile to a user and organisation', function (): void
{
    $profile = UserProfile::factory()->create();

    expect($profile->user)->toBeInstanceOf(User::class);
    expect($profile->organisation)->toBeInstanceOf(Organisation::class);
});

it('generates a project key scoped to the customer and a 64-char token', function (): void
{
    $project = Project::factory()->create(['name' => 'Member Portal']);

    expect($project->key)->toBe('member-portal');
    expect(mb_strlen($project->token))->toBe(64);
    expect($project->customer)->not->toBeNull();
});

it('scopes duplicate project keys per customer', function (): void
{
    $first = Project::factory()->create(['name' => 'Member Portal']);
    $second = Project::factory()->create([
        'name' => 'Member Portal',
        'customer_id' => $first->customer_id,
    ]);

    expect($second->key)->toBe('member-portal-2');
});

it('creates release notes linked to a history and author', function (): void
{
    $note = ReleaseNote::factory()->create();

    expect($note->releaseHistory)->not->toBeNull();
    expect($note->author)->toBeInstanceOf(User::class);
});

it('creates customer contacts linked to a customer', function (): void
{
    $contact = CustomerContact::factory()->create();

    expect($contact->customer)->not->toBeNull();
});
