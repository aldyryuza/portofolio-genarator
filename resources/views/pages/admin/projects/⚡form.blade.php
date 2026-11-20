<?php

use App\Actions\Projects\SaveProject;
use App\Enums\ProjectStatus;
use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillGroup;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    private const NULLABLE_TEXT = [
        'slug', 'excerpt', 'summary', 'challenge', 'solution', 'outcome', 'role',
        'client_label', 'start_date', 'end_date', 'meta_title', 'meta_description',
    ];

    #[Locked]
    public ?int $projectId = null;

    public string $title = '';

    public ?string $slug = null;

    public ?string $excerpt = null;

    public ?string $summary = null;

    public ?string $challenge = null;

    public ?string $solution = null;

    public ?string $outcome = null;

    public ?string $role = null;

    public ?string $client_label = null;

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?int $category_id = null;

    /** @var array<int, int|string> */
    public array $skill_ids = [];

    /** @var array<int, int|string> */
    public array $tag_ids = [];

    public string $status = 'draft';

    public bool $is_featured = false;

    public ?string $meta_title = null;

    public ?string $meta_description = null;

    public function mount(?Project $project = null): void
    {
        if (! $project?->exists) {
            $this->authorize('create', Project::class);

            return;
        }

        $this->authorize('update', $project);

        $this->projectId = $project->id;
        $this->fill($project->only([
            'title', 'slug', 'excerpt', 'summary', 'challenge', 'solution', 'outcome',
            'role', 'client_label', 'category_id', 'is_featured', 'meta_title', 'meta_description',
        ]));
        $this->start_date = $project->start_date?->format('Y-m-d');
        $this->end_date = $project->end_date?->format('Y-m-d');
        $this->status = $project->status->value;
        $this->skill_ids = $project->skills()->pluck('skills.id')->all();
        $this->tag_ids = $project->tags()->pluck('tags.id')->all();
    }

    public function render()
    {
        return $this->view()->title($this->projectId ? __('Edit project') : __('New project'));
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->ordered()->get();
    }

    /**
     * @return Collection<int, SkillGroup>
     */
    #[Computed]
    public function skillGroups(): Collection
    {
        return SkillGroup::query()->with(['skills' => fn ($query) => $query->ordered()])->ordered()->get();
    }

    /**
     * @return Collection<int, Tag>
     */
    #[Computed]
    public function tags(): Collection
    {
        return Tag::query()->orderBy('name')->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Project::class)->ignore($this->projectId)],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'challenge' => ['nullable', 'string', 'max:10000'],
            'solution' => ['nullable', 'string', 'max:10000'],
            'outcome' => ['nullable', 'string', 'max:10000'],
            'role' => ['nullable', 'string', 'max:120'],
            'client_label' => ['nullable', 'string', 'max:120'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => array_filter(['nullable', 'date_format:Y-m-d', filled($this->start_date) ? 'after_or_equal:start_date' : null]),
            'category_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')],
            'skill_ids' => ['array'],
            'skill_ids.*' => ['integer', 'distinct', Rule::exists(Skill::class, 'id')],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists(Tag::class, 'id')],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'is_featured' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function save(SaveProject $saveProject): void
    {
        $project = $this->projectId ? Project::findOrFail($this->projectId) : new Project;
        $this->authorize($project->exists ? 'update' : 'create', $project->exists ? $project : Project::class);

        foreach (self::NULLABLE_TEXT as $field) {
            if (is_string($this->{$field}) && trim($this->{$field}) === '') {
                $this->{$field} = null;
            }
        }

        $validated = $this->validate();
        $validated['skill_ids'] = array_map('intval', $validated['skill_ids']);
        $validated['tag_ids'] = array_map('intval', $validated['tag_ids']);

        /** @var User $user */
        $user = Auth::user();
        $isNew = ! $project->exists;
        $project = $saveProject->handle($project, $validated, $user);

        session()->flash('status', __('Project saved.'));

        if ($isNew) {
            $this->redirectRoute('admin.projects.edit', $project, navigate: true);

            return;
        }

        $this->slug = $project->slug;
    }
};
?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ route('admin.projects.index') }}" wire:navigate class="text-sm text-ink-muted hover:text-ink">← {{ __('Projects') }}</a>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $projectId ? __('Edit project') : __('New project') }}</h1>
        </div>
        @if ($projectId)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.projects.media', $projectId) }}" wire:navigate class="btn-secondary">{{ __('Images & videos') }}</a>
                {{-- Signed, expiring link: safe to share with a reviewer before publishing. --}}
                <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('projects.preview', now()->addDays(7), ['project' => $projectId]) }}" target="_blank" rel="noopener" class="btn-secondary">{{ __('Preview') }} ↗</a>
                @if ($status === 'published' && $slug)
                    <a href="{{ route('projects.show', $slug) }}" target="_blank" rel="noopener" class="btn-secondary">{{ __('View live') }} ↗</a>
                @endif
            </div>
        @endif
    </div>

    <x-flash class="mt-4" />

    <form wire:submit="save" class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]" novalidate>
        <div class="space-y-6">
            <section class="card space-y-4" aria-labelledby="basics-heading">
                <h2 id="basics-heading" class="text-base font-semibold">{{ __('Basics') }}</h2>
                <x-form.input name="title" :label="__('Title')" maxlength="160" required />
                <x-form.input name="slug" :label="__('Slug')" maxlength="180"
                    :hint="$status === 'published' ? __('Changing the slug of a published project breaks existing links.') : __('Lowercase letters, numbers and dashes. Leave empty to generate from the title.')" />
                <x-form.textarea name="excerpt" :label="__('Excerpt')" rows="2" maxlength="300" :hint="__('One or two sentences for project cards. Up to 300 characters.')" />
            </section>

            <section class="card space-y-4" aria-labelledby="story-heading">
                <h2 id="story-heading" class="text-base font-semibold">{{ __('Case study') }}</h2>
                <p class="text-sm text-ink-muted">{{ __('Plain text. Describe only real work and outcomes you can verify.') }}</p>
                <x-form.textarea name="summary" :label="__('Summary')" rows="4" maxlength="10000" />
                <x-form.textarea name="challenge" :label="__('Challenge')" rows="4" maxlength="10000" />
                <x-form.textarea name="solution" :label="__('Solution & responsibilities')" rows="5" maxlength="10000" />
                <x-form.textarea name="outcome" :label="__('Outcome & impact')" rows="4" maxlength="10000" />
            </section>

            <section class="card space-y-4" aria-labelledby="seo-heading">
                <h2 id="seo-heading" class="text-base font-semibold">{{ __('Search & sharing') }}</h2>
                <x-form.input name="meta_title" :label="__('Meta title')" maxlength="70" :hint="__('Up to 70 characters. Defaults to the title.')" />
                <x-form.textarea name="meta_description" :label="__('Meta description')" rows="2" maxlength="160" :hint="__('Up to 160 characters. Defaults to the excerpt.')" />
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card space-y-4" aria-labelledby="publish-heading">
                <h2 id="publish-heading" class="text-base font-semibold">{{ __('Publishing') }}</h2>
                <x-form.select name="status" :label="__('Status')">
                    @foreach (\App\Enums\ProjectStatus::cases() as $option)
                        <option value="{{ $option->value }}" wire:key="status-{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </x-form.select>
                <x-form.checkbox name="is_featured" :label="__('Feature on the home page')" />
                <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">{{ __('Save project') }}</button>
            </section>

            <section class="card space-y-4" aria-labelledby="details-heading">
                <h2 id="details-heading" class="text-base font-semibold">{{ __('Details') }}</h2>
                <x-form.select name="category_id" :label="__('Category')">
                    <option value="">{{ __('No category') }}</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}" wire:key="category-{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="role" :label="__('Your role')" maxlength="120" />
                <x-form.input name="client_label" :label="__('Client label')" maxlength="120" :hint="__('Optional. Use a generic label if the client is confidential.')" />
                <div class="grid grid-cols-2 gap-3">
                    <x-form.input name="start_date" type="date" :label="__('Start date')" />
                    <x-form.input name="end_date" type="date" :label="__('End date')" />
                </div>
                <p class="form-hint">{{ __('Leave the end date empty for ongoing projects.') }}</p>
            </section>

            <fieldset class="card space-y-3">
                <legend class="text-base font-semibold">{{ __('Skills') }}</legend>
                @forelse ($this->skillGroups as $group)
                    <div wire:key="skill-group-{{ $group->id }}">
                        <p class="text-xs font-medium tracking-wide text-ink-muted uppercase">{{ $group->name }}</p>
                        <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1.5">
                            @foreach ($group->skills as $skill)
                                <label wire:key="skill-{{ $skill->id }}" class="flex items-center gap-1.5 text-sm">
                                    <input type="checkbox" wire:model="skill_ids" value="{{ $skill->id }}" class="size-4 rounded border-line-strong accent-accent">
                                    {{ $skill->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-ink-muted">{{ __('No skills yet.') }} <a href="{{ route('admin.skills') }}" wire:navigate class="text-accent hover:underline">{{ __('Add skills') }}</a></p>
                @endforelse
                @error('skill_ids.*') <p class="form-error">{{ $message }}</p> @enderror
            </fieldset>

            <fieldset class="card space-y-3">
                <legend class="text-base font-semibold">{{ __('Tags') }}</legend>
                <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                    @forelse ($this->tags as $tag)
                        <label wire:key="tag-{{ $tag->id }}" class="flex items-center gap-1.5 text-sm">
                            <input type="checkbox" wire:model="tag_ids" value="{{ $tag->id }}" class="size-4 rounded border-line-strong accent-accent">
                            {{ $tag->name }}
                        </label>
                    @empty
                        <p class="text-sm text-ink-muted">{{ __('No tags yet.') }} <a href="{{ route('admin.tags') }}" wire:navigate class="text-accent hover:underline">{{ __('Add tags') }}</a></p>
                    @endforelse
                </div>
                @error('tag_ids.*') <p class="form-error">{{ $message }}</p> @enderror
            </fieldset>
        </aside>
    </form>
</div>
