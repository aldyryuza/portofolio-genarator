<?php

use App\Http\Requests\ContactRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->owner = User::factory()->owner()->create();
});

/**
 * A submission as a real visitor makes it: token rendered, then a few seconds of typing.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactPayload(array $overrides = []): array
{
    $token = ContactRequest::freshToken();
    test()->travel(10)->seconds();

    return [
        'name' => 'Jane Visitor',
        'email' => 'jane@example.com',
        'subject' => 'Project enquiry',
        'message' => 'Hello, I would like to discuss a project with you.',
        'website' => '',
        'form_token' => $token,
        ...$overrides,
    ];
}

test('the contact page renders the form with its spam traps', function () {
    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('name="form_token"', escape: false)
        ->assertSee('name="website"', escape: false)
        ->assertSee('name="_token"', escape: false);
});

test('a valid message is stored and the owner is notified', function () {
    $this->from(route('contact'))
        ->post(route('contact.store'), contactPayload())
        ->assertRedirect(route('contact'))
        ->assertSessionHas('status');

    $message = ContactMessage::sole();

    expect($message)
        ->sender_email->toBe('jane@example.com')
        ->ip_hash->toBe(hash_hmac('sha256', '127.0.0.1', config('app.key')))
        ->ip_hash->not->toContain('127.0.0.1');

    Notification::assertSentTo($this->owner, ContactMessageReceived::class, function (ContactMessageReceived $notification) {
        $mail = $notification->toMail($this->owner);

        return $mail->replyTo === [['jane@example.com', 'Jane Visitor']];
    });
});

test('spam submissions look successful but are dropped', function (Closure $payload) {
    $this->from(route('contact'))
        ->post(route('contact.store'), $payload())
        ->assertRedirect(route('contact'))
        ->assertSessionHas('status');

    expect(ContactMessage::count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'honeypot filled' => [fn () => contactPayload(['website' => 'https://spam.test'])],
    'submitted too fast' => [fn () => [...contactPayload(), 'form_token' => ContactRequest::freshToken()]],
    'forged token' => [fn () => contactPayload(['form_token' => 'not-encrypted'])],
    'missing token' => [fn () => contactPayload(['form_token' => null])],
    'stale token' => [function () {
        $token = ContactRequest::freshToken();
        test()->travel(3)->hours();

        return contactPayload(['form_token' => $token]);
    }],
]);

test('invalid input is rejected with errors and old input', function (array $overrides, string $field) {
    $this->from(route('contact'))
        ->post(route('contact.store'), contactPayload($overrides))
        ->assertRedirect(route('contact'))
        ->assertSessionHasErrors($field)
        ->assertSessionHasInput('email');

    expect(ContactMessage::count())->toBe(0);
})->with([
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'header injection in email' => [['email' => "jane@example.com\r\nBcc: victim@example.com"], 'email'],
    'message too short' => [['message' => 'hi'], 'message'],
    'message too long' => [['message' => str_repeat('a', 5001)], 'message'],
    'missing name' => [['name' => ''], 'name'],
]);

test('the contact form is throttled per IP', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post(route('contact.store'), contactPayload())->assertRedirect();
    }

    $this->post(route('contact.store'), contactPayload())->assertTooManyRequests();
    expect(ContactMessage::count())->toBe(3);
});
