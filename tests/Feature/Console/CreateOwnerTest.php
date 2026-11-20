<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('creates the owner with a prompted password', function () {
    $this->artisan('portfolio:create-owner', ['--name' => 'Site Owner', '--email' => 'owner@example.com'])
        ->expectsQuestion('Password', 'a-strong-password')
        ->expectsQuestion('Confirm password', 'a-strong-password')
        ->assertSuccessful();

    $owner = User::sole();

    expect($owner->is_owner)->toBeTrue()
        ->and($owner->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-strong-password', $owner->password))->toBeTrue();
});

test('refuses to create a second owner', function () {
    User::factory()->owner()->create();

    $this->artisan('portfolio:create-owner', ['--name' => 'Another', '--email' => 'another@example.com'])
        ->assertFailed();

    expect(User::count())->toBe(1);
});

test('rejects invalid input and creates nothing', function (string $email, string $password, string $confirmation) {
    $this->artisan('portfolio:create-owner', ['--name' => 'Site Owner', '--email' => $email])
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $confirmation)
        ->assertFailed();

    expect(User::count())->toBe(0);
})->with([
    'invalid email' => ['not-an-email', 'a-strong-password', 'a-strong-password'],
    'mismatched confirmation' => ['owner@example.com', 'a-strong-password', 'different-password'],
    'too short password' => ['owner@example.com', 'short', 'short'],
]);
