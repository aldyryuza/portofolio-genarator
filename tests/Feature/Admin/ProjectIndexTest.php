<?php

use App\Enums\ProjectStatus;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

test('owner can publish and unpublish a project from the list', function () {
    $project = Project::factory()->create();

    $component = Livewire::actingAs($this->owner)->test('pages::admin.projects.index');

    $component->call('togglePublished', $project->id);
    expect($project->fresh()->isPublished())->toBeTrue();

    $component->call('togglePublished', $project->id);
    expect($project->fresh())
        ->status->toBe(ProjectStatus::Draft)
        ->published_at->not->toBeNull();

    expect(ActivityLog::pluck('event')->all())->toBe(['project.published', 'project.unpublished']);
});

test('owner can toggle featured', function () {
    $project = Project::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.index')
        ->call('toggleFeatured', $project->id);

    expect($project->fresh()->is_featured)->toBeTrue();
});

test('owner can move projects up and down', function () {
    [$first, $second, $third] = [
        Project::factory()->create(['sort_order' => 1]),
        Project::factory()->create(['sort_order' => 2]),
        Project::factory()->create(['sort_order' => 3]),
    ];

    $component = Livewire::actingAs($this->owner)->test('pages::admin.projects.index');

    $component->call('move', $third->id, 'up');
    expect(Project::ordered()->pluck('id')->all())->toBe([$first->id, $third->id, $second->id]);

    $component->call('move', $first->id, 'up'); // already first: no-op
    $component->call('move', $first->id, 'down');
    expect(Project::ordered()->pluck('id')->all())->toBe([$third->id, $first->id, $second->id])
        ->and(Project::ordered()->pluck('sort_order')->all())->toBe([1, 2, 3]);
});

test('moving ignores unknown directions', function () {
    $first = Project::factory()->create(['sort_order' => 1]);
    $second = Project::factory()->create(['sort_order' => 2]);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.index')
        ->call('move', $second->id, 'sideways');

    expect(Project::ordered()->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('deleting moves a project to trash and it can be restored', function () {
    $project = Project::factory()->published()->create();

    $component = Livewire::actingAs($this->owner)->test('pages::admin.projects.index');

    $component->call('delete', $project->id);
    expect($project->fresh()->trashed())->toBeTrue()
        ->and(Project::published()->count())->toBe(0);

    $component->set('filter', 'trashed')
        ->assertSee($project->title)
        ->call('restore', $project->id);

    expect($project->fresh()->trashed())->toBeFalse();
});

test('list can be filtered by status and searched by title', function () {
    $draft = Project::factory()->create(['title' => '[DEMO] Alpha draft']);
    $live = Project::factory()->published()->create(['title' => '[DEMO] Beta live']);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.index')
        ->set('filter', 'published')
        ->assertSee($live->title)
        ->assertDontSee($draft->title)
        ->set('filter', '')
        ->set('search', 'Alpha')
        ->assertSee($draft->title)
        ->assertDontSee($live->title);
});

test('search treats SQL wildcards literally', function () {
    Project::factory()->create(['title' => '[DEMO] Plain']);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.index')
        ->set('search', '%')
        ->assertSee('No projects found.');
});

test('non-owners cannot use the project list component', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::admin.projects.index')
        ->assertForbidden();
});
