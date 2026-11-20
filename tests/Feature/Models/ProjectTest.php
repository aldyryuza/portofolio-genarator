<?php

use App\Enums\ProjectStatus;
use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillGroup;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;

test('published scope only returns live, non-deleted published projects', function () {
    $live = Project::factory()->published()->create();
    Project::factory()->create(); // draft
    Project::factory()->published()->create(['published_at' => now()->addDay()]); // scheduled
    Project::factory()->published()->create()->delete(); // trashed

    expect(Project::published()->pluck('id')->all())->toBe([$live->id]);
});

test('re-publishing keeps the original publish date', function () {
    $project = Project::factory()->published()->create(['published_at' => now()->subMonth()]);
    $originalDate = $project->published_at;

    $project->unpublish();
    $project->save();
    $project->publish();
    $project->save();

    expect($project->fresh())
        ->status->toBe(ProjectStatus::Published)
        ->published_at->toEqual($originalDate);
});

test('visitors may view published projects only, owner may view drafts', function () {
    $draft = Project::factory()->create();
    $published = Project::factory()->published()->create();
    $owner = User::factory()->owner()->create();
    $user = User::factory()->create();

    expect(Gate::forUser(null)->allows('view', $published))->toBeTrue()
        ->and(Gate::forUser(null)->allows('view', $draft))->toBeFalse()
        ->and($user->can('view', $draft))->toBeFalse()
        ->and($owner->can('view', $draft))->toBeTrue();
});

test('unique slugs skip slugs held by soft-deleted projects', function () {
    Project::factory()->create(['slug' => 'my-project']);
    Project::factory()->create(['slug' => 'my-project-2'])->delete();

    expect(Project::uniqueSlug('My Project'))->toBe('my-project-3')
        ->and(Project::uniqueSlug('My Project', Project::where('slug', 'my-project')->value('id')))->toBe('my-project')
        ->and(Project::uniqueSlug('!!!'))->toBe('item');
});

test('deleting a category keeps its projects uncategorised', function () {
    $category = Category::factory()->create();
    $project = Project::factory()->create(['category_id' => $category->id]);

    $category->delete();

    expect($project->fresh()->category_id)->toBeNull();
});

test('a skill group with skills cannot be deleted at the database level', function () {
    $group = SkillGroup::factory()->create();
    Skill::factory()->create(['skill_group_id' => $group->id]);

    $group->delete();
})->throws(QueryException::class);

test('a project cannot be linked to the same skill twice', function () {
    $project = Project::factory()->create();
    $skill = Skill::factory()->create();

    $project->skills()->attach($skill);
    $project->skills()->attach($skill);
})->throws(QueryException::class);
