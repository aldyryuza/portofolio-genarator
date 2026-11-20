<?php

use App\Models\ActivityLog;
use App\Models\Education;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Education')] class extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $institution = '';

    public ?string $degree = null;

    public ?string $field_of_study = null;

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?string $description = null;

    public ?int $sort_order = 0;

    public bool $is_published = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Education::class);
    }

    /**
     * @return Collection<int, Education>
     */
    #[Computed]
    public function educations(): Collection
    {
        return Education::query()->chronological()->get();
    }

    public function edit(int $id): void
    {
        $education = Education::findOrFail($id);
        $this->authorize('update', $education);

        $this->resetValidation();
        $this->editingId = $education->id;
        $this->fill($education->only(['institution', 'degree', 'field_of_study', 'description', 'sort_order', 'is_published']));
        $this->start_date = $education->start_date?->format('Y-m-d');
        $this->end_date = $education->end_date?->format('Y-m-d');
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->reset();
    }

    public function save(): void
    {
        $education = $this->editingId ? Education::findOrFail($this->editingId) : new Education;
        $this->authorize($education->exists ? 'update' : 'create', $education->exists ? $education : Education::class);

        foreach (['degree', 'field_of_study', 'start_date', 'end_date', 'description'] as $field) {
            if (is_string($this->{$field}) && trim($this->{$field}) === '') {
                $this->{$field} = null;
            }
        }

        $validated = $this->validate([
            'institution' => ['required', 'string', 'max:160'],
            'degree' => ['nullable', 'string', 'max:160'],
            'field_of_study' => ['nullable', 'string', 'max:160'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            // An empty end date means ongoing (or not shown).
            'end_date' => array_filter(['nullable', 'date_format:Y-m-d', filled($this->start_date) ? 'after_or_equal:start_date' : null]),
            'description' => ['nullable', 'string', 'max:3000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_published' => ['boolean'],
        ]);

        $education->fill($validated);
        $isNew = ! $education->exists;
        $changed = array_keys($education->getDirty());
        $education->save();

        ActivityLog::record(Auth::user(), $isNew ? 'education.created' : 'education.updated', $education, $isNew ? [] : ['changed' => $changed]);

        $this->cancel();
        session()->flash('status', __('Education saved.'));
    }

    public function delete(int $id): void
    {
        $education = Education::findOrFail($id);
        $this->authorize('delete', $education);

        $education->delete();
        ActivityLog::record(Auth::user(), 'education.deleted', $education, ['institution' => $education->institution]);

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('status', __('Education deleted.'));
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Education') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Degrees and courses, shown newest first. Leave the end date empty for ongoing studies.') }}</p>
    </div>

    <x-flash />

    <div class="grid gap-6 lg:grid-cols-[24rem_1fr]">
        <form wire:submit="save" class="card h-fit space-y-4" novalidate>
            <h2 class="text-base font-semibold">{{ $editingId ? __('Edit education') : __('New education') }}</h2>

            <x-form.input name="institution" :label="__('Institution')" maxlength="160" required />
            <x-form.input name="degree" :label="__('Degree')" maxlength="160" />
            <x-form.input name="field_of_study" :label="__('Field of study')" maxlength="160" />
            <div class="grid grid-cols-2 gap-3">
                <x-form.input name="start_date" type="date" :label="__('Start date')" />
                <x-form.input name="end_date" type="date" :label="__('End date')" />
            </div>
            <x-form.textarea name="description" :label="__('Description')" rows="3" maxlength="3000" />
            <div class="grid grid-cols-2 items-end gap-3">
                <x-form.input name="sort_order" type="number" :label="__('Tie-break order')" min="0" />
                <x-form.checkbox name="is_published" :label="__('Published')" />
            </div>

            <div class="flex justify-end gap-2 border-t border-line pt-4">
                @if ($editingId)
                    <button type="button" wire:click="cancel" class="btn-secondary">{{ __('Cancel') }}</button>
                @endif
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Save') }}</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-line bg-surface">
            @if ($this->educations->isEmpty())
                <p class="p-6 text-sm text-ink-muted">{{ __('No education yet.') }}</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Institution') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Period') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->educations as $education)
                            <tr wire:key="education-{{ $education->id }}">
                                <td class="px-4 py-2.5">
                                    <span class="font-medium">{{ $education->institution }}</span>
                                    @unless ($education->is_published)
                                        <x-badge class="ms-1">{{ __('Draft') }}</x-badge>
                                    @endunless
                                    <span class="block text-xs text-ink-muted">{{ collect([$education->degree, $education->field_of_study])->filter()->implode(' · ') }}</span>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-ink-muted">
                                    {{ $education->start_date?->format('Y') ?? '—' }} – {{ $education->end_date?->format('Y') ?? ($education->isOngoing() ? __('Present') : '—') }}
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <button type="button" wire:click="edit({{ $education->id }})" class="text-accent hover:underline">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="delete({{ $education->id }})" wire:confirm="{{ __('Delete this entry?') }}" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
