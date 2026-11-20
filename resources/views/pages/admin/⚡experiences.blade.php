<?php

use App\Enums\EmploymentType;
use App\Models\ActivityLog;
use App\Models\Experience;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Experience')] class extends Component
{
    private const MAX_ACHIEVEMENTS = 12;

    #[Locked]
    public ?int $editingId = null;

    public string $company = '';

    public string $title = '';

    public string $employment_type = 'full_time';

    public ?string $location = null;

    public ?string $start_date = null;

    public ?string $end_date = null;

    public bool $is_current = false;

    public ?string $description = null;

    /** One achievement per line; stored as a JSON list of plain strings. */
    public ?string $achievements = null;

    public ?int $sort_order = 0;

    public bool $is_published = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Experience::class);
    }

    /**
     * @return Collection<int, Experience>
     */
    #[Computed]
    public function experiences(): Collection
    {
        return Experience::query()->chronological()->get();
    }

    public function edit(int $id): void
    {
        $experience = Experience::findOrFail($id);
        $this->authorize('update', $experience);

        $this->resetValidation();
        $this->editingId = $experience->id;
        $this->fill($experience->only(['company', 'title', 'location', 'is_current', 'description', 'sort_order', 'is_published']));
        $this->employment_type = $experience->employment_type->value;
        $this->start_date = $experience->start_date->format('Y-m-d');
        $this->end_date = $experience->end_date?->format('Y-m-d');
        $this->achievements = implode("\n", $experience->achievements_json ?? []);
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->reset();
    }

    public function save(): void
    {
        $experience = $this->editingId ? Experience::findOrFail($this->editingId) : new Experience;
        $this->authorize($experience->exists ? 'update' : 'create', $experience->exists ? $experience : Experience::class);

        $validated = $this->validate([
            'company' => ['required', 'string', 'max:160'],
            'title' => ['required', 'string', 'max:160'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'location' => ['nullable', 'string', 'max:120'],
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            // A current role has no end date; a past role must have one.
            'end_date' => $this->is_current
                ? ['prohibited']
                : array_filter(['required', 'date_format:Y-m-d', 'before_or_equal:today', filled($this->start_date) ? 'after_or_equal:start_date' : null]),
            'is_current' => ['boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
            'achievements' => ['nullable', 'string', 'max:5000', function (string $attribute, mixed $value, Closure $fail): void {
                $lines = $this->achievementLines();

                if (count($lines) > self::MAX_ACHIEVEMENTS) {
                    $fail(__('Add at most :max achievements.', ['max' => self::MAX_ACHIEVEMENTS]));
                } elseif (collect($lines)->contains(fn (string $line): bool => mb_strlen($line) > 300)) {
                    $fail(__('Each achievement can be at most 300 characters.'));
                }
            }],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_published' => ['boolean'],
        ], ['end_date.prohibited' => __('Leave the end date empty for a current role.')]);

        $experience->fill([
            ...collect($validated)->except('achievements')->all(),
            'location' => filled($validated['location']) ? $validated['location'] : null,
            'description' => filled($validated['description']) ? $validated['description'] : null,
            'end_date' => $this->is_current ? null : $validated['end_date'],
            'achievements_json' => $this->achievementLines() ?: null,
        ]);

        $isNew = ! $experience->exists;
        $changed = array_keys($experience->getDirty());
        $experience->save();

        ActivityLog::record(Auth::user(), $isNew ? 'experience.created' : 'experience.updated', $experience, $isNew ? [] : ['changed' => $changed]);

        $this->cancel();
        session()->flash('status', __('Experience saved.'));
    }

    public function delete(int $id): void
    {
        $experience = Experience::findOrFail($id);
        $this->authorize('delete', $experience);

        $experience->delete();
        ActivityLog::record(Auth::user(), 'experience.deleted', $experience, ['company' => $experience->company]);

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('status', __('Experience deleted.'));
    }

    /**
     * @return list<string>
     */
    private function achievementLines(): array
    {
        return collect(preg_split('/\R/', (string) $this->achievements) ?: [])
            ->map(fn (string $line): string => trim($line, " \t-•*"))
            ->filter()
            ->values()
            ->all();
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Experience') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Work history, shown newest first. Only add roles and achievements that are true and verifiable.') }}</p>
    </div>

    <x-flash />

    <div class="grid gap-6 lg:grid-cols-[26rem_1fr]">
        <form wire:submit="save" class="card h-fit space-y-4" novalidate>
            <h2 class="text-base font-semibold">{{ $editingId ? __('Edit role') : __('New role') }}</h2>

            <x-form.input name="title" :label="__('Job title')" maxlength="160" required />
            <x-form.input name="company" :label="__('Company')" maxlength="160" required />
            <div class="grid grid-cols-2 gap-3">
                <x-form.select name="employment_type" :label="__('Type')">
                    @foreach (\App\Enums\EmploymentType::cases() as $type)
                        <option value="{{ $type->value }}" wire:key="type-{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="location" :label="__('Location')" maxlength="120" />
            </div>
            <x-form.checkbox name="is_current" :label="__('I currently work here')" live />
            <div class="grid grid-cols-2 gap-3">
                <x-form.input name="start_date" type="date" :label="__('Start date')" required />
                @unless ($is_current)
                    <x-form.input name="end_date" type="date" :label="__('End date')" required />
                @endunless
            </div>
            <x-form.textarea name="description" :label="__('Description')" rows="4" maxlength="5000" />
            <x-form.textarea name="achievements" :label="__('Achievements')" rows="4" :hint="__('One per line, up to 12. Plain text.')" />
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
            @if ($this->experiences->isEmpty())
                <p class="p-6 text-sm text-ink-muted">{{ __('No experience yet.') }}</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Role') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Period') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->experiences as $experience)
                            <tr wire:key="experience-{{ $experience->id }}">
                                <td class="px-4 py-2.5">
                                    <span class="font-medium">{{ $experience->title }}</span>
                                    @unless ($experience->is_published)
                                        <x-badge class="ms-1">{{ __('Draft') }}</x-badge>
                                    @endunless
                                    <span class="block text-xs text-ink-muted">{{ $experience->company }} · {{ $experience->employment_type->label() }}</span>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-ink-muted">
                                    {{ $experience->start_date->format('M Y') }} – {{ $experience->is_current ? __('Present') : $experience->end_date?->format('M Y') }}
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <button type="button" wire:click="edit({{ $experience->id }})" class="text-accent hover:underline">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="delete({{ $experience->id }})" wire:confirm="{{ __('Delete this role?') }}" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
