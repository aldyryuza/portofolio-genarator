<?php

use App\Models\ActivityLog;
use App\Models\SkillGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Skill groups')] class extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public ?string $slug = null;

    public ?int $sort_order = 0;

    public function mount(): void
    {
        $this->authorize('viewAny', SkillGroup::class);
    }

    /**
     * @return Collection<int, SkillGroup>
     */
    #[Computed]
    public function groups(): Collection
    {
        return SkillGroup::query()->withCount('skills')->ordered()->get();
    }

    public function edit(int $id): void
    {
        $group = SkillGroup::findOrFail($id);
        $this->authorize('update', $group);

        $this->resetValidation();
        $this->editingId = $group->id;
        $this->fill($group->only(['name', 'slug', 'sort_order']));
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->reset();
    }

    public function save(): void
    {
        $group = $this->editingId ? SkillGroup::findOrFail($this->editingId) : new SkillGroup;
        $this->authorize($group->exists ? 'update' : 'create', $group->exists ? $group : SkillGroup::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(SkillGroup::class)->ignore($group->id)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        $group->fill($validated);
        $group->slug = filled($validated['slug']) ? $validated['slug'] : ($group->slug ?? SkillGroup::uniqueSlug($validated['name']));
        $isNew = ! $group->exists;
        $changed = array_keys($group->getDirty());
        $group->save();

        ActivityLog::record(Auth::user(), $isNew ? 'skill_group.created' : 'skill_group.updated', $group, $isNew ? [] : ['changed' => $changed]);

        $this->cancel();
        session()->flash('status', __('Skill group saved.'));
    }

    public function delete(int $id): void
    {
        $group = SkillGroup::withCount('skills')->findOrFail($id);
        $this->authorize('delete', $group);

        if ($group->skills_count > 0) {
            $this->addError('delete', __('Move or delete the skills in this group first.'));

            return;
        }

        $group->delete();
        ActivityLog::record(Auth::user(), 'skill_group.deleted', $group, ['name' => $group->name]);

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('status', __('Skill group deleted.'));
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Skill groups') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ __('Sections such as "Backend" or "Design" that organise skills.') }}</p>
        </div>
        <a href="{{ route('admin.skills') }}" wire:navigate class="btn-secondary">{{ __('Back to skills') }}</a>
    </div>

    <x-flash />
    @error('delete') <p class="form-error" role="alert">{{ $message }}</p> @enderror

    <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
        <form wire:submit="save" class="card h-fit space-y-4" novalidate>
            <h2 class="text-base font-semibold">{{ $editingId ? __('Edit group') : __('New group') }}</h2>

            <x-form.input name="name" :label="__('Name')" maxlength="100" required />
            <x-form.input name="slug" :label="__('Slug')" maxlength="120" :hint="__('Leave empty to generate.')" />
            <x-form.input name="sort_order" type="number" :label="__('Sort order')" min="0" class="max-w-32" />

            <div class="flex justify-end gap-2 border-t border-line pt-4">
                @if ($editingId)
                    <button type="button" wire:click="cancel" class="btn-secondary">{{ __('Cancel') }}</button>
                @endif
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Save') }}</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-line bg-surface">
            @if ($this->groups->isEmpty())
                <p class="p-6 text-sm text-ink-muted">{{ __('No skill groups yet.') }}</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Skills') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Order') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->groups as $group)
                            <tr wire:key="group-{{ $group->id }}">
                                <td class="px-4 py-2.5">
                                    <span class="font-medium">{{ $group->name }}</span>
                                    <span class="block font-mono text-xs text-ink-muted">{{ $group->slug }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-ink-muted">{{ $group->skills_count }}</td>
                                <td class="px-4 py-2.5 text-ink-muted">{{ $group->sort_order }}</td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <button type="button" wire:click="edit({{ $group->id }})" class="text-accent hover:underline">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="delete({{ $group->id }})" wire:confirm="{{ __('Delete this skill group?') }}" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
