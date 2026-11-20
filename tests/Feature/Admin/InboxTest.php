<?php

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

test('opening a message marks it as read', function () {
    $message = ContactMessage::factory()->create(['body' => 'Please call me.']);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.inbox')
        ->call('open', $message->id)
        ->assertSee('Please call me.');

    expect($message->fresh())
        ->status->toBe(ContactMessageStatus::Read)
        ->read_at->not->toBeNull();
});

test('re-opening a read message keeps its original read time', function () {
    $message = ContactMessage::factory()->create(['status' => 'read', 'read_at' => now()->subDay()]);

    Livewire::actingAs($this->owner)->test('pages::admin.inbox')->call('open', $message->id);

    expect($message->fresh()->read_at->isYesterday())->toBeTrue();
});

test('messages can be archived, restored and deleted', function () {
    $message = ContactMessage::factory()->create(['subject' => 'Archive me']);

    $component = Livewire::actingAs($this->owner)
        ->test('pages::admin.inbox')
        ->call('toggleArchived', $message->id)
        ->assertDontSee('Archive me');

    expect($message->fresh()->status)->toBe(ContactMessageStatus::Archived);

    $component->set('filter', 'archived')->assertSee('Archive me')->call('toggleArchived', $message->id);
    expect($message->fresh()->status)->toBe(ContactMessageStatus::Read);

    $component->call('delete', $message->id);
    expect(ContactMessage::count())->toBe(0);
});

test('visitor input is escaped in the inbox', function () {
    $message = ContactMessage::factory()->create(['body' => '<script>alert("x")</script>', 'sender_name' => '<img src=x onerror=alert(1)>']);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.inbox')
        ->call('open', $message->id)
        ->assertDontSee('<script>alert("x")</script>', escape: false)
        ->assertDontSee('<img src=x onerror=alert(1)>', escape: false);
});

test('unread messages are counted in the admin navigation', function () {
    ContactMessage::factory()->count(2)->create();
    ContactMessage::factory()->create(['status' => 'read']);

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertSee('2 new messages')
        ->assertSee('2<span class="sr-only"> unread</span>', escape: false);
});

test('non-owners cannot read the inbox', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::admin.inbox')
        ->assertForbidden();
});
