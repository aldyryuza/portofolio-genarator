<?php

use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\MediaAsset;
use App\Models\Profile;
use App\Models\SocialLink;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

test('owner can add a past role with achievements', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.experiences')
        ->set([
            'title' => '[DEMO] Backend Engineer',
            'company' => '[DEMO] Acme',
            'start_date' => '2019-02-01',
            'end_date' => '2021-06-30',
            'achievements' => "- Cut API latency\n\n• Led migration  \n",
            'is_published' => true,
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(Experience::sole())
        ->achievements_json->toBe(['Cut API latency', 'Led migration'])
        ->is_current->toBeFalse()
        ->end_date->format('Y-m-d')->toBe('2021-06-30');
});

test('a current role has no end date', function () {
    $component = Livewire::actingAs($this->owner)
        ->test('pages::admin.experiences')
        ->set(['title' => 'Role', 'company' => 'Co', 'start_date' => '2023-01-01', 'is_current' => true, 'end_date' => '2024-01-01'])
        ->call('save')
        ->assertHasErrors(['end_date' => 'prohibited']);

    $component->set('end_date', null)->call('save')->assertHasNoErrors();

    expect(Experience::sole())->is_current->toBeTrue()->end_date->toBeNull();
});

test('experience dates are validated', function (array $input, string $field) {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.experiences')
        ->set(['title' => 'Role', 'company' => 'Co', 'start_date' => '2020-01-01', 'end_date' => '2021-01-01', ...$input])
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'past role without end date' => [['end_date' => null], 'end_date'],
    'end before start' => [['end_date' => '2019-01-01'], 'end_date'],
    'start in the future' => [['start_date' => now()->addYear()->format('Y-m-d'), 'end_date' => null, 'is_current' => true], 'start_date'],
    'too many achievements' => [['achievements' => implode("\n", range(1, 13))], 'achievements'],
    'achievement too long' => [['achievements' => str_repeat('a', 301)], 'achievements'],
    'unknown employment type' => [['employment_type' => 'volunteer-ish'], 'employment_type'],
]);

test('experience is ordered current first, then by most recent end date', function () {
    $old = Experience::factory()->create(['start_date' => '2015-01-01', 'end_date' => '2017-01-01']);
    $current = Experience::factory()->current()->create(['start_date' => '2022-01-01']);
    $recent = Experience::factory()->create(['start_date' => '2018-01-01', 'end_date' => '2021-12-31']);

    expect(Experience::chronological()->pluck('id')->all())->toBe([$current->id, $recent->id, $old->id]);
});

test('education is ordered ongoing first and end date may be empty', function () {
    $finished = Education::factory()->create(['end_date' => '2018-07-01']);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.educations')
        ->set(['institution' => '[DEMO] Online Academy', 'start_date' => '2024-01-01', 'end_date' => ''])
        ->call('save')
        ->assertHasNoErrors();

    $ongoing = Education::where('institution', '[DEMO] Online Academy')->sole();

    expect($ongoing->isOngoing())->toBeTrue()
        ->and(Education::chronological()->pluck('id')->all())->toBe([$ongoing->id, $finished->id]);
});

test('certification links and files are validated', function (array $input, string $field) {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.certifications')
        ->set(['name' => 'Cert', 'issuer' => 'Issuer', 'issue_date' => '2023-01-01', ...$input])
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'javascript url' => [['credential_url' => 'javascript:alert(1)'], 'credential_url'],
    'data url' => [['credential_url' => 'data:text/html,hi'], 'credential_url'],
    'expiry before issue' => [['expiry_date' => '2022-01-01'], 'expiry_date'],
    'unknown file' => [['file_media_id' => 999], 'file_media_id'],
]);

test('a deleted media file cannot be attached to a certification', function () {
    $media = MediaAsset::factory()->create();
    $media->delete();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.certifications')
        ->set(['name' => 'Cert', 'issuer' => 'Issuer', 'issue_date' => '2023-01-01', 'file_media_id' => $media->id])
        ->call('save')
        ->assertHasErrors('file_media_id');
});

test('owner can add a certification and it reports expiry', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.certifications')
        ->set(['name' => '[DEMO] Cloud Cert', 'issuer' => '[DEMO] Vendor', 'issue_date' => '2020-01-01', 'expiry_date' => '2023-01-01', 'credential_url' => 'https://verify.example.com/abc'])
        ->call('save')
        ->assertHasNoErrors();

    expect(Certification::sole()->isExpired())->toBeTrue();
});

test('a media file used by a certification cannot be deleted', function () {
    $media = MediaAsset::factory()->create();
    Certification::factory()->create(['file_media_id' => $media->id]);

    expect($media->isReferenced())->toBeTrue();
});

test('social links accept only web urls', function (string $url, bool $valid) {
    Profile::factory()->create(['user_id' => $this->owner->id]);

    $component = Livewire::actingAs($this->owner)
        ->test('pages::admin.social-links')
        ->set(['platform' => 'github', 'url' => $url])
        ->call('save');

    $valid ? $component->assertHasNoErrors() : $component->assertHasErrors('url');
})->with([
    'https' => ['https://github.com/someone', true],
    'javascript' => ['javascript:alert(document.cookie)', false],
    'mailto' => ['mailto:someone@example.com', false],
    'no scheme' => ['github.com/someone', false],
]);

test('social links require a saved profile', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.social-links')
        ->set(['platform' => 'github', 'url' => 'https://github.com/someone'])
        ->call('save')
        ->assertHasErrors('url');

    expect(SocialLink::count())->toBe(0);
});

test('links of another profile cannot be edited', function () {
    Profile::factory()->create(['user_id' => $this->owner->id]);
    $foreign = SocialLink::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.social-links')
        ->call('delete', $foreign->id)
        ->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});

test('non-owners cannot use career components', function (string $component) {
    Livewire::actingAs(User::factory()->create())
        ->test($component)
        ->assertForbidden();
})->with(['pages::admin.experiences', 'pages::admin.educations', 'pages::admin.certifications', 'pages::admin.social-links']);
