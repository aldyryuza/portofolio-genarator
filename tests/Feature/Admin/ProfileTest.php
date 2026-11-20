<?php

use App\Enums\AvailabilityStatus;
use App\Models\ActivityLog;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Validation\Rules\Enum;
use Livewire\Livewire;

test('owner can create the public profile', function () {
    $owner = User::factory()->owner()->create(['name' => 'Owner Name']);

    Livewire::actingAs($owner)
        ->test('pages::admin.profile')
        ->assertSet('display_name', 'Owner Name')
        ->set('display_name', '[DEMO] Display Name')
        ->set('headline', '[DEMO] Engineer')
        ->set('public_email', 'hello@example.com')
        ->set('availability_status', AvailabilityStatus::OpenToProjects->value)
        ->call('save')
        ->assertHasNoErrors();

    $profile = $owner->fresh()->profile;

    expect($profile)
        ->display_name->toBe('[DEMO] Display Name')
        ->headline->toBe('[DEMO] Engineer')
        ->availability_status->toBe(AvailabilityStatus::OpenToProjects);

    $log = ActivityLog::where('event', 'profile.updated')->sole();
    expect($log->actor_id)->toBe($owner->id)
        ->and($log->properties_json['changed'])->toContain('display_name', 'headline');
});

test('blank optional fields are stored as null', function () {
    $profile = Profile::factory()->create(['headline' => 'Old headline']);

    Livewire::actingAs($profile->user)
        ->test('pages::admin.profile')
        ->set('headline', '   ')
        ->call('save')
        ->assertHasNoErrors();

    expect($profile->fresh()->headline)->toBeNull();
});

test('saving without changes does not write an activity log', function () {
    $profile = Profile::factory()->create();

    Livewire::actingAs($profile->user)
        ->test('pages::admin.profile')
        ->call('save')
        ->assertHasNoErrors();

    expect(ActivityLog::count())->toBe(0);
});

test('profile input is validated', function (string $field, mixed $value, string $rule) {
    Livewire::actingAs(User::factory()->owner()->create())
        ->test('pages::admin.profile')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors([$field => $rule]);
})->with([
    'display name required' => ['display_name', '', 'required'],
    'display name too long' => ['display_name', str_repeat('a', 121), 'max'],
    'headline too long' => ['headline', str_repeat('a', 161), 'max'],
    'summary too long' => ['summary', str_repeat('a', 501), 'max'],
    'biography too long' => ['biography', str_repeat('a', 5001), 'max'],
    'invalid public email' => ['public_email', 'not-an-email', 'email'],
    'unknown availability' => ['availability_status', 'hacker', Enum::class],
]);

test('profile content is escaped when rendered', function () {
    $profile = Profile::factory()->create(['headline' => '<script>alert(1)</script>']);

    $this->actingAs($profile->user)
        ->get(route('admin.profile'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

test('non-owner cannot update a profile via the policy', function () {
    $profile = Profile::factory()->create();
    $other = User::factory()->create();

    expect($other->can('update', $profile))->toBeFalse()
        ->and($profile->user->can('update', $profile))->toBeTrue();
});

test('an owner cannot update a profile that belongs to another user', function () {
    $profile = Profile::factory()->create();
    $otherOwner = User::factory()->owner()->create();

    expect($otherOwner->can('update', $profile))->toBeFalse();
});

test('profile component rejects non-owners even when reached outside the route', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::admin.profile')
        ->assertForbidden();

    expect(Profile::count())->toBe(0);
});
