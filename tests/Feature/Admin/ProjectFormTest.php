<?php

use App\Enums\ProjectStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Tag;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

test('owner can create a draft project with taxonomy', function () {
    $category = Category::factory()->create();
    $skills = Skill::factory()->count(2)->create();
    $tag = Tag::factory()->create();

    $component = Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.form')
        ->set('title', '[DEMO] Inventory Dashboard')
        ->set('category_id', $category->id)
        ->set('skill_ids', $skills->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->set('tag_ids', [$tag->id])
        ->set('start_date', '2024-01-01')
        ->call('save')
        ->assertHasNoErrors();

    $project = Project::sole();
    $component->assertRedirect(route('admin.projects.edit', $project));

    expect($project)
        ->slug->toBe('demo-inventory-dashboard')
        ->status->toBe(ProjectStatus::Draft)
        ->published_at->toBeNull()
        ->category_id->toBe($category->id)
        ->end_date->toBeNull()
        ->and($project->skills->pluck('id')->sort()->values()->all())->toBe($skills->pluck('id')->sort()->values()->all())
        ->and($project->tags->pluck('id')->all())->toBe([$tag->id])
        ->and(ActivityLog::where('event', 'project.created')->where('subject_id', $project->id)->exists())->toBeTrue();
});

test('new projects are appended to the end of the order', function () {
    Project::factory()->create(['sort_order' => 7]);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.form')
        ->set('title', '[DEMO] Last')
        ->call('save');

    expect(Project::where('title', '[DEMO] Last')->value('sort_order'))->toBe(8);
});

test('publishing from the form sets the publish date', function () {
    $this->freezeSecond();
    $project = Project::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.form', ['project' => $project])
        ->set('status', ProjectStatus::Published->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($project->fresh())
        ->status->toBe(ProjectStatus::Published)
        ->published_at->toEqual(now());
});

test('editing loads existing data and an empty slug keeps the current slug', function () {
    $skill = Skill::factory()->create();
    $project = Project::factory()->create(['slug' => 'stable-url', 'start_date' => '2023-05-01']);
    $project->skills()->attach($skill);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.form', ['project' => $project])
        ->assertSet('start_date', '2023-05-01')
        ->assertSet('skill_ids', [$skill->id])
        ->set('title', '[DEMO] Renamed')
        ->set('slug', '')
        ->set('skill_ids', [])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('slug', 'stable-url');

    expect($project->fresh())->slug->toBe('stable-url')->title->toBe('[DEMO] Renamed')
        ->and($project->skills()->count())->toBe(0);

    $changed = ActivityLog::where('event', 'project.updated')->sole()->properties_json['changed'];
    expect($changed)->toContain('title', 'skills')->not->toContain('updated_at');
});

test('slug must be unique, including against trashed projects', function () {
    Project::factory()->create(['slug' => 'taken'])->delete();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.form')
        ->set('title', '[DEMO] Project')
        ->set('slug', 'taken')
        ->call('save')
        ->assertHasErrors(['slug' => 'unique']);
});

test('project input is validated', function (array $input, string $field) {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.form')
        ->set(['title' => '[DEMO] Project', ...$input])
        ->call('save')
        ->assertHasErrors($field);

    expect(Project::count())->toBe(0);
})->with([
    'missing title' => [['title' => ''], 'title'],
    'slug with spaces' => [['slug' => 'Not A Slug'], 'slug'],
    'slug with path traversal' => [['slug' => '../admin'], 'slug'],
    'end before start' => [['start_date' => '2024-05-01', 'end_date' => '2024-01-01'], 'end_date'],
    'invalid date format' => [['start_date' => '01/05/2024'], 'start_date'],
    'unknown category' => [['category_id' => 999], 'category_id'],
    'unknown skill' => [['skill_ids' => [999]], 'skill_ids.0'],
    'unknown tag' => [['tag_ids' => [999]], 'tag_ids.0'],
    'unknown status' => [['status' => 'archived'], 'status'],
    'meta description too long' => [['meta_description' => str_repeat('a', 161)], 'meta_description'],
]);

test('trashed projects cannot be opened for editing', function () {
    $project = Project::factory()->create();
    $project->delete();

    $this->actingAs($this->owner)
        ->get(route('admin.projects.edit', $project))
        ->assertNotFound();
});

test('non-owners cannot use the project form component', function () {
    $project = Project::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::admin.projects.form', ['project' => $project])
        ->assertForbidden();
});

test('the project id cannot be swapped from the client', function () {
    $project = Project::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.form', ['project' => $project])
        ->set('projectId', 999);
})->throws(CannotUpdateLockedPropertyException::class);

test('changing a slug remembers the old one for redirects', function () {
    $project = Project::factory()->create(['slug' => 'first-name']);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.form', ['project' => $project])
        ->set('slug', 'second-name')
        ->call('save')
        ->set('slug', 'first-name')
        ->call('save')
        ->assertHasNoErrors();

    // Going back to a previous slug must not leave a redirect pointing at itself.
    expect($project->fresh()->slug)->toBe('first-name')
        ->and($project->slugRedirects()->pluck('slug')->all())->toBe(['second-name']);
});
