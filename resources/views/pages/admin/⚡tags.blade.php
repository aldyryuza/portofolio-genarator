<?php

use App\Models\ActivityLog;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tags')] class extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public ?string $slug = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Tag::class);
    }

    /**
     * @return Collection<int, Tag>
     */
    #[Computed]
    public function tags(): Collection
    {
        return Tag::query()->withCount('projects')->orderBy('name')->get();
    }

    public function edit(int $id): void
    {
        $tag = Tag::findOrFail($id);
        $this->authorize('update', $tag);

        $this->resetValidation();
        $this->editingId = $tag->id;
        $this->fill($tag->only(['name', 'slug']));
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->reset();
    }

    public function save(): void
    {
        $tag = $this->editingId ? Tag::findOrFail($this->editingId) : new Tag;
        $this->authorize($tag->exists ? 'update' : 'create', $tag->exists ? $tag : Tag::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Tag::class)->ignore($tag->id)],
        ]);

        $tag->name = $validated['name'];
        $tag->slug = filled($validated['slug']) ? $validated['slug'] : ($tag->slug ?? Tag::uniqueSlug($validated['name']));
        $isNew = ! $tag->exists;
        $changed = array_keys($tag->getDirty());
        $tag->save();

        ActivityLog::record(Auth::user(), $isNew ? 'tag.created' : 'tag.updated', $tag, $isNew ? [] : ['changed' => $changed]);

        $this->cancel();
        session()->flash('status', __('Tag saved.'));
    }

    public function delete(int $id): void
    {
        $tag = Tag::findOrFail($id);
        $this->authorize('delete', $tag);

        $tag->delete();
        ActivityLog::record(Auth::user(), 'tag.deleted', $tag, ['name' => $tag->name]);

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('status', __('Tag deleted.'));
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Tags') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Free-form labels attached to projects.') }}</p>
    </div>

    <x-flash />

    <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
        <form wire:submit="save" class="card h-fit space-y-4" novalidate>
            <h2 class="text-base font-semibold">{{ $editingId ? __('Edit tag') : __('New tag') }}</h2>

            <x-form.input name="name" :label="__('Name')" maxlength="60" required />
            <x-form.input name="slug" :label="__('Slug')" maxlength="80" :hint="__('Leave empty to generate.')" />

            <div class="flex justify-end gap-2 border-t border-line pt-4">
                @if ($editingId)
                    <button type="button" wire:click="cancel" class="btn-secondary">{{ __('Cancel') }}</button>
                @endif
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Save') }}</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-line bg-surface">
            @if ($this->tags->isEmpty())
                <p class="p-6 text-sm text-ink-muted">{{ __('No tags yet.') }}</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Projects') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->tags as $tag)
                            <tr wire:key="tag-{{ $tag->id }}">
                                <td class="px-4 py-2.5">
                                    <span class="font-medium">{{ $tag->name }}</span>
                                    <span class="block font-mono text-xs text-ink-muted">{{ $tag->slug }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-ink-muted">{{ $tag->projects_count }}</td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <button type="button" wire:click="edit({{ $tag->id }})" class="text-accent hover:underline">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="delete({{ $tag->id }})" wire:confirm="{{ __('Delete this tag? It will be removed from all projects.') }}" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
