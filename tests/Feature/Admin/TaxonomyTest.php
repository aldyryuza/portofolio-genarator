<?php

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillGroup;
use App\Models\Tag;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

test('owner can create, edit and delete a category', function () {
    $component = Livewire::actingAs($this->owner)
        ->test('pages::admin.categories')
        ->set('name', '[DEMO] Web Apps')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('name', '');

    $category = Category::sole();
    expect($category->slug)->toBe('demo-web-apps');

    $component->call('edit', $category->id)
        ->assertSet('editingId', $category->id)
        ->set('name', '[DEMO] Web Applications')
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($category->fresh())
        ->name->toBe('[DEMO] Web Applications')
        ->slug->toBe('demo-web-apps')
        ->is_active->toBeFalse();

    $component->call('delete', $category->id);
    expect(Category::count())->toBe(0)
        ->and(ActivityLog::pluck('event')->all())->toBe(['category.created', 'category.updated', 'category.deleted']);
});

test('generated slugs stay unique', function () {
    Category::factory()->create(['slug' => 'design']);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.categories')
        ->set('name', 'Design')
        ->call('save');

    expect(Category::where('name', 'Design')->value('slug'))->toBe('design-2');
});

test('taxonomy slugs are validated', function (string $component, string $slug, string $rule) {
    Category::factory()->create(['slug' => 'taken']);
    Tag::factory()->create(['slug' => 'taken']);

    Livewire::actingAs($this->owner)
        ->test($component)
        ->set('name', 'Name')
        ->set('slug', $slug)
        ->call('save')
        ->assertHasErrors(['slug' => $rule]);
})->with([
    'category duplicate' => ['pages::admin.categories', 'taken', 'unique'],
    'category bad format' => ['pages::admin.categories', 'Bad Slug', 'regex'],
    'tag duplicate' => ['pages::admin.tags', 'taken', 'unique'],
    'tag bad format' => ['pages::admin.tags', '<b>x</b>', 'regex'],
]);

test('deleting a tag detaches it from projects', function () {
    $tag = Tag::factory()->create();
    $project = Project::factory()->create();
    $project->tags()->attach($tag);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.tags')
        ->call('delete', $tag->id);

    expect($project->tags()->count())->toBe(0);
});

test('a skill group that still has skills cannot be deleted', function () {
    $group = SkillGroup::factory()->create();
    Skill::factory()->create(['skill_group_id' => $group->id]);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.skill-groups')
        ->call('delete', $group->id)
        ->assertHasErrors('delete');

    expect($group->fresh())->not->toBeNull();
});

test('an empty skill group can be deleted', function () {
    $group = SkillGroup::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.skill-groups')
        ->call('delete', $group->id)
        ->assertHasNoErrors();

    expect(SkillGroup::count())->toBe(0);
});

test('owner can create a skill in a group', function () {
    $group = SkillGroup::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.skills')
        ->set('skill_group_id', $group->id)
        ->set('name', 'Laravel')
        ->set('icon', 'laravel')
        ->set('proficiency', 4)
        ->call('save')
        ->assertHasNoErrors();

    expect(Skill::sole())
        ->skill_group_id->toBe($group->id)
        ->slug->toBe('laravel')
        ->proficiency->toBe(4)
        ->is_published->toBeTrue();
});

test('skill input is validated', function (string $field, mixed $value) {
    $group = SkillGroup::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.skills')
        ->set(['skill_group_id' => $group->id, 'name' => 'Skill', $field => $value])
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'unknown group' => ['skill_group_id', 999],
    'icon with markup' => ['icon', '<svg onload=alert(1)>'],
    'proficiency too high' => ['proficiency', 6],
    'proficiency too low' => ['proficiency', 0],
    'negative sort order' => ['sort_order', -1],
]);

test('editing state cannot be tampered with from the client', function (string $component) {
    Livewire::actingAs($this->owner)
        ->test($component)
        ->set('editingId', 1);
})->with(['pages::admin.categories', 'pages::admin.tags', 'pages::admin.skills', 'pages::admin.skill-groups'])
    ->throws(CannotUpdateLockedPropertyException::class);

test('non-owners cannot use taxonomy components', function (string $component) {
    Livewire::actingAs(User::factory()->create())
        ->test($component)
        ->assertForbidden();
})->with(['pages::admin.categories', 'pages::admin.tags', 'pages::admin.skills', 'pages::admin.skill-groups']);
