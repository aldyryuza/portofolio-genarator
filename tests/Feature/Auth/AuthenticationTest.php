<?php

use App\Models\User;
use Livewire\Livewire;

test('login screen can be rendered', function () {
    $this->get(route('login'))->assertOk()->assertSee('Owner log in');
});

test('owner can log in and is redirected to the dashboard', function () {
    $owner = User::factory()->owner()->create();

    Livewire::test('pages::auth.login')
        ->set('email', $owner->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($owner);
});

test('owner cannot log in with a wrong password', function () {
    $owner = User::factory()->owner()->create();

    Livewire::test('pages::auth.login')
        ->set('email', $owner->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email')
        ->assertSet('password', '');

    $this->assertGuest();
});

test('non-owner users cannot log in even with a correct password', function () {
    $user = User::factory()->create();

    Livewire::test('pages::auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

test('login is rate limited after five failed attempts', function () {
    $owner = User::factory()->owner()->create();

    $component = Livewire::test('pages::auth.login')->set('email', $owner->email);

    foreach (range(1, 5) as $attempt) {
        $component->set('password', 'wrong-password')->call('login');
    }

    $component->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email')
        ->assertSee('Too many login attempts');

    $this->assertGuest();
});

test('owner can log out', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('logout requires a POST request', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get('/logout')
        ->assertMethodNotAllowed();
});

test('authenticated owner visiting login is redirected to the dashboard', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('login'))
        ->assertRedirect(route('admin.dashboard'));
});

test('public registration is not available', function (string $method) {
    $this->{$method}('/register')->assertNotFound();
})->with(['get', 'post']);
