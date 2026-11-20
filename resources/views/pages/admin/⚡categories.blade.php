<?php

use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Categories')] class extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public ?string $slug = null;

    public ?string $description = null;

    public ?int $sort_order = 0;

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Category::class);
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->withCount('projects')->ordered()->get();
    }

    public function edit(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->authorize('update', $category);

        $this->resetValidation();
        $this->editingId = $category->id;
        $this->fill($category->only(['name', 'slug', 'description', 'sort_order', 'is_active']));
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->reset();
    }

    public function save(): void
    {
        $category = $this->editingId ? Category::findOrFail($this->editingId) : new Category;
        $this->authorize($category->exists ? 'update' : 'create', $category->exists ? $category : Category::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Category::class)->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
        ]);

        $category->fill($validated);
        $category->slug = filled($validated['slug']) ? $validated['slug'] : ($category->slug ?? Category::uniqueSlug($validated['name']));
        $isNew = ! $category->exists;
        $changed = array_keys($category->getDirty());
        $category->save();

        ActivityLog::record(Auth::user(), $isNew ? 'category.created' : 'category.updated', $category, $isNew ? [] : ['changed' => $changed]);

        $this->cancel();
        session()->flash('status', __('Category saved.'));
    }

    public function delete(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->authorize('delete', $category);

        // Projects keep existing; their category_id is set to null by the foreign key.
        $category->delete();
        ActivityLog::record(Auth::user(), 'category.deleted', $category, ['name' => $category->name]);

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('status', __('Category deleted.'));
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Categories') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Group projects for filtering on the public site. Inactive categories are hidden publicly.') }}</p>
    </div>

    <x-flash />

    <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
        <form wire:submit="save" class="card h-fit space-y-4" novalidate>
            <h2 class="text-base font-semibold">{{ $editingId ? __('Edit category') : __('New category') }}</h2>

            <x-form.input name="name" :label="__('Name')" maxlength="100" required />
            <x-form.input name="slug" :label="__('Slug')" maxlength="120" :hint="__('Lowercase letters, numbers and dashes. Leave empty to generate.')" />
            <x-form.textarea name="description" :label="__('Description')" rows="3" maxlength="1000" />
            <x-form.input name="sort_order" type="number" :label="__('Sort order')" min="0" class="max-w-32" />
            <x-form.checkbox name="is_active" :label="__('Active')" />

            <div class="flex justify-end gap-2 border-t border-line pt-4">
                @if ($editingId)
                    <button type="button" wire:click="cancel" class="btn-secondary">{{ __('Cancel') }}</button>
                @endif
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Save') }}</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-line bg-surface">
            @if ($this->categories->isEmpty())
                <p class="p-6 text-sm text-ink-muted">{{ __('No categories yet.') }}</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Projects') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Order') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->categories as $category)
                            <tr wire:key="category-{{ $category->id }}">
                                <td class="px-4 py-2.5">
                                    <span class="font-medium">{{ $category->name }}</span>
                                    @unless ($category->is_active)
                                        <x-badge class="ms-1">{{ __('Inactive') }}</x-badge>
                                    @endunless
                                    <span class="block font-mono text-xs text-ink-muted">{{ $category->slug }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-ink-muted">{{ $category->projects_count }}</td>
                                <td class="px-4 py-2.5 text-ink-muted">{{ $category->sort_order }}</td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <button type="button" wire:click="edit({{ $category->id }})" class="text-accent hover:underline">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="delete({{ $category->id }})" wire:confirm="{{ __('Delete this category? Its projects will become uncategorised.') }}" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
