<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

const RESET_STATUS = 'If that email belongs to the owner account, a reset link has been sent.';

test('forgot password screen can be rendered', function () {
    $this->get(route('password.request'))->assertOk();
});

test('owner receives a reset link', function () {
    Notification::fake();
    $owner = User::factory()->owner()->create();

    Livewire::test('pages::auth.forgot-password')
        ->set('email', $owner->email)
        ->call('sendResetLink')
        ->assertHasNoErrors()
        ->assertSee(RESET_STATUS);

    Notification::assertSentTo($owner, ResetPassword::class);
});

test('unknown and non-owner emails get the same response and no email', function (bool $existingNonOwner) {
    Notification::fake();
    $email = $existingNonOwner ? User::factory()->create()->email : 'nobody@example.com';

    Livewire::test('pages::auth.forgot-password')
        ->set('email', $email)
        ->call('sendResetLink')
        ->assertHasNoErrors()
        ->assertSee(RESET_STATUS);

    Notification::assertNothingSent();
})->with(['unknown email' => false, 'non-owner email' => true]);

test('reset link requests are rate limited per IP', function () {
    Notification::fake();

    $component = Livewire::test('pages::auth.forgot-password');

    foreach (range(1, 5) as $attempt) {
        $component->set('email', "user{$attempt}@example.com")->call('sendResetLink')->assertHasNoErrors();
    }

    $component->set('email', 'user6@example.com')
        ->call('sendResetLink')
        ->assertHasErrors('email');
});

test('owner can reset the password with a valid token', function () {
    $owner = User::factory()->owner()->create();
    $token = Password::createToken($owner);

    $this->get(route('password.reset', ['token' => $token, 'email' => $owner->email]))->assertOk();

    Livewire::test('pages::auth.reset-password', ['token' => $token])
        ->set('email', $owner->email)
        ->set('password', 'new-secure-password')
        ->set('password_confirmation', 'new-secure-password')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('new-secure-password', $owner->fresh()->password))->toBeTrue();
    expect(ActivityLog::where('event', 'account.password_reset')->exists())->toBeTrue();
});

test('password is not reset with an invalid token', function () {
    $owner = User::factory()->owner()->create();

    Livewire::test('pages::auth.reset-password', ['token' => 'invalid-token'])
        ->set('email', $owner->email)
        ->set('password', 'new-secure-password')
        ->set('password_confirmation', 'new-secure-password')
        ->call('resetPassword')
        ->assertHasErrors('email');

    expect(Hash::check('password', $owner->fresh()->password))->toBeTrue();
});

test('reset token cannot be tampered with from the client', function () {
    Livewire::test('pages::auth.reset-password', ['token' => 'original'])
        ->set('token', 'tampered');
})->throws(CannotUpdateLockedPropertyException::class);
