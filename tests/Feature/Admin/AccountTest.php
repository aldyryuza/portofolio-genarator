<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('owner can change the name without re-entering the password', function () {
    $owner = User::factory()->owner()->create();

    Livewire::actingAs($owner)
        ->test('pages::admin.account')
        ->set('name', 'New Name')
        ->call('updateAccount')
        ->assertHasNoErrors();

    expect($owner->fresh()->name)->toBe('New Name');
    expect(ActivityLog::where('event', 'account.updated')->sole()->properties_json['changed'])->toBe(['name']);
});

test('changing the login email requires the current password', function () {
    $owner = User::factory()->owner()->create(['email' => 'old@example.com']);

    Livewire::actingAs($owner)
        ->test('pages::admin.account')
        ->set('email', 'new@example.com')
        ->call('updateAccount')
        ->assertHasErrors(['account_current_password' => 'required'])
        ->set('account_current_password', 'wrong-password')
        ->call('updateAccount')
        ->assertHasErrors(['account_current_password' => 'current_password']);

    expect($owner->fresh()->email)->toBe('old@example.com');

    Livewire::actingAs($owner)
        ->test('pages::admin.account')
        ->set('email', 'new@example.com')
        ->set('account_current_password', 'password')
        ->call('updateAccount')
        ->assertHasNoErrors();

    expect($owner->fresh()->email)->toBe('new@example.com');
});

test('login email must be unique', function () {
    $owner = User::factory()->owner()->create();
    $other = User::factory()->create();

    Livewire::actingAs($owner)
        ->test('pages::admin.account')
        ->set('email', $other->email)
        ->set('account_current_password', 'password')
        ->call('updateAccount')
        ->assertHasErrors(['email' => 'unique']);
});

test('owner can change the password with the current password', function () {
    $owner = User::factory()->owner()->create();

    Livewire::actingAs($owner)
        ->test('pages::admin.account')
        ->set('current_password', 'password')
        ->set('password', 'new-secure-password')
        ->set('password_confirmation', 'new-secure-password')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSet('current_password', '')
        ->assertSet('password', '');

    expect(Hash::check('new-secure-password', $owner->fresh()->password))->toBeTrue();
    expect(ActivityLog::where('event', 'account.password_changed')->exists())->toBeTrue();
});

test('password change fails with a wrong current password or mismatched confirmation', function (string $current, string $confirmation, string $field) {
    $owner = User::factory()->owner()->create();

    Livewire::actingAs($owner)
        ->test('pages::admin.account')
        ->set('current_password', $current)
        ->set('password', 'new-secure-password')
        ->set('password_confirmation', $confirmation)
        ->call('updatePassword')
        ->assertHasErrors($field);

    expect(Hash::check('password', $owner->fresh()->password))->toBeTrue();
})->with([
    'wrong current password' => ['wrong-password', 'new-secure-password', 'current_password'],
    'mismatched confirmation' => ['password', 'something-else', 'password'],
]);
