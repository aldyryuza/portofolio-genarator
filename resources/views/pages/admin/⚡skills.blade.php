<?php

use App\Models\ActivityLog;
use App\Models\Skill;
use App\Models\SkillGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Skills')] class extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public ?int $skill_group_id = null;

    public string $name = '';

    public ?string $slug = null;

    public ?string $icon = null;

    public ?int $proficiency = null;

    public ?int $sort_order = 0;

    public bool $is_published = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Skill::class);
    }

    /**
     * @return Collection<int, SkillGroup>
     */
    #[Computed]
    public function groups(): Collection
    {
        return SkillGroup::query()
            ->with(['skills' => fn ($query) => $query->withCount('projects')->ordered()])
            ->ordered()
            ->get();
    }

    public function edit(int $id): void
    {
        $skill = Skill::findOrFail($id);
        $this->authorize('update', $skill);

        $this->resetValidation();
        $this->editingId = $skill->id;
        $this->fill($skill->only(['skill_group_id', 'name', 'slug', 'icon', 'proficiency', 'sort_order', 'is_published']));
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->reset();
    }

    public function save(): void
    {
        $skill = $this->editingId ? Skill::findOrFail($this->editingId) : new Skill;
        $this->authorize($skill->exists ? 'update' : 'create', $skill->exists ? $skill : Skill::class);

        $validated = $this->validate([
            'skill_group_id' => ['required', 'integer', Rule::exists(SkillGroup::class, 'id')],
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Skill::class)->ignore($skill->id)],
            // Icon is a key into a known icon set, never raw SVG/HTML.
            'icon' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:[-.][a-z0-9]+)*$/'],
            'proficiency' => ['nullable', 'integer', 'min:1', 'max:'.Skill::MAX_PROFICIENCY],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_published' => ['boolean'],
        ]);

        $skill->fill($validated);
        $skill->slug = filled($validated['slug']) ? $validated['slug'] : ($skill->slug ?? Skill::uniqueSlug($validated['name']));
        $isNew = ! $skill->exists;
        $changed = array_keys($skill->getDirty());
        $skill->save();

        ActivityLog::record(Auth::user(), $isNew ? 'skill.created' : 'skill.updated', $skill, $isNew ? [] : ['changed' => $changed]);

        $this->cancel();
        session()->flash('status', __('Skill saved.'));
    }

    public function delete(int $id): void
    {
        $skill = Skill::findOrFail($id);
        $this->authorize('delete', $skill);

        $skill->delete();
        ActivityLog::record(Auth::user(), 'skill.deleted', $skill, ['name' => $skill->name]);

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('status', __('Skill deleted.'));
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Skills') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ __('Technologies and capabilities, linked to projects. Hidden skills are not shown publicly.') }}</p>
        </div>
        <a href="{{ route('admin.skill-groups') }}" wire:navigate class="btn-secondary">{{ __('Manage groups') }}</a>
    </div>

    <x-flash />

    @if ($this->groups->isEmpty())
        <div class="card text-sm text-ink-muted">
            {{ __('Create a skill group first.') }}
            <a href="{{ route('admin.skill-groups') }}" wire:navigate class="text-accent hover:underline">{{ __('Add a group') }}</a>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
            <form wire:submit="save" class="card h-fit space-y-4" novalidate>
                <h2 class="text-base font-semibold">{{ $editingId ? __('Edit skill') : __('New skill') }}</h2>

                <x-form.select name="skill_group_id" :label="__('Group')" required>
                    <option value="">{{ __('Choose a group') }}</option>
                    @foreach ($this->groups as $group)
                        <option value="{{ $group->id }}" wire:key="group-option-{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="name" :label="__('Name')" maxlength="100" required />
                <x-form.input name="slug" :label="__('Slug')" maxlength="120" :hint="__('Leave empty to generate.')" />
                <x-form.input name="icon" :label="__('Icon key')" maxlength="64" :hint="__('Optional, e.g. laravel or tailwindcss.')" />
                <div class="grid grid-cols-2 gap-4">
                    <x-form.input name="proficiency" type="number" :label="__('Proficiency (1–5)')" min="1" :max="\App\Models\Skill::MAX_PROFICIENCY" />
                    <x-form.input name="sort_order" type="number" :label="__('Sort order')" min="0" />
                </div>
                <x-form.checkbox name="is_published" :label="__('Visible on public site')" />

                <div class="flex justify-end gap-2 border-t border-line pt-4">
                    @if ($editingId)
                        <button type="button" wire:click="cancel" class="btn-secondary">{{ __('Cancel') }}</button>
                    @endif
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Save') }}</button>
                </div>
            </form>

            <div class="space-y-4">
                @foreach ($this->groups as $group)
                    <section wire:key="group-{{ $group->id }}" class="overflow-x-auto rounded-lg border border-line bg-surface" aria-labelledby="group-heading-{{ $group->id }}">
                        <h2 id="group-heading-{{ $group->id }}" class="border-b border-line px-4 py-2.5 text-sm font-semibold">{{ $group->name }}</h2>
                        @if ($group->skills->isEmpty())
                            <p class="px-4 py-3 text-sm text-ink-muted">{{ __('No skills in this group.') }}</p>
                        @else
                            <table class="w-full text-left text-sm">
                                <thead class="sr-only">
                                    <tr>
                                        <th scope="col">{{ __('Name') }}</th>
                                        <th scope="col">{{ __('Projects') }}</th>
                                        <th scope="col">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($group->skills as $skill)
                                        <tr wire:key="skill-{{ $skill->id }}">
                                            <td class="px-4 py-2.5">
                                                <span class="font-medium">{{ $skill->name }}</span>
                                                @unless ($skill->is_published)
                                                    <x-badge class="ms-1">{{ __('Hidden') }}</x-badge>
                                                @endunless
                                            </td>
                                            <td class="px-4 py-2.5 text-ink-muted">{{ trans_choice(':count project|:count projects', $skill->projects_count) }}</td>
                                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                                <button type="button" wire:click="edit({{ $skill->id }})" class="text-accent hover:underline">{{ __('Edit') }}</button>
                                                <button type="button" wire:click="delete({{ $skill->id }})" wire:confirm="{{ __('Delete this skill? It will be removed from all projects.') }}" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </section>
                @endforeach
            </div>
        </div>
    @endif
</div>
