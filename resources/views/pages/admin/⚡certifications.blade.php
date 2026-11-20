<?php

use App\Models\ActivityLog;
use App\Models\Certification;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Certifications')] class extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $issuer = '';

    public ?string $issue_date = null;

    public ?string $expiry_date = null;

    public ?string $credential_id = null;

    public ?string $credential_url = null;

    public ?int $file_media_id = null;

    public bool $is_published = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Certification::class);
    }

    /**
     * @return Collection<int, Certification>
     */
    #[Computed]
    public function certifications(): Collection
    {
        return Certification::query()->with('file')->chronological()->get();
    }

    /**
     * @return Collection<int, MediaAsset>
     */
    #[Computed]
    public function files(): Collection
    {
        return MediaAsset::query()->latest('id')->limit(100)->get();
    }

    public function edit(int $id): void
    {
        $certification = Certification::findOrFail($id);
        $this->authorize('update', $certification);

        $this->resetValidation();
        $this->editingId = $certification->id;
        $this->fill($certification->only(['name', 'issuer', 'credential_id', 'credential_url', 'file_media_id', 'is_published']));
        $this->issue_date = $certification->issue_date->format('Y-m-d');
        $this->expiry_date = $certification->expiry_date?->format('Y-m-d');
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->reset();
    }

    public function save(): void
    {
        $certification = $this->editingId ? Certification::findOrFail($this->editingId) : new Certification;
        $this->authorize($certification->exists ? 'update' : 'create', $certification->exists ? $certification : Certification::class);

        foreach (['expiry_date', 'credential_id', 'credential_url'] as $field) {
            if (is_string($this->{$field}) && trim($this->{$field}) === '') {
                $this->{$field} = null;
            }
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'issuer' => ['required', 'string', 'max:160'],
            'issue_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'expiry_date' => array_filter(['nullable', 'date_format:Y-m-d', filled($this->issue_date) ? 'after:issue_date' : null]),
            'credential_id' => ['nullable', 'string', 'max:120'],
            // Only web links; blocks javascript:, data: and similar schemes.
            'credential_url' => ['nullable', 'string', 'max:500', 'url:https,http'],
            'file_media_id' => ['nullable', 'integer', Rule::exists(MediaAsset::class, 'id')->whereNull('deleted_at')],
            'is_published' => ['boolean'],
        ]);

        $certification->fill($validated);
        $isNew = ! $certification->exists;
        $changed = array_keys($certification->getDirty());
        $certification->save();

        ActivityLog::record(Auth::user(), $isNew ? 'certification.created' : 'certification.updated', $certification, $isNew ? [] : ['changed' => $changed]);

        $this->cancel();
        session()->flash('status', __('Certification saved.'));
    }

    public function delete(int $id): void
    {
        $certification = Certification::findOrFail($id);
        $this->authorize('delete', $certification);

        $certification->delete();
        ActivityLog::record(Auth::user(), 'certification.deleted', $certification, ['name' => $certification->name]);

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('status', __('Certification deleted.'));
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Certifications') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Only list credentials you actually hold. Visitors see the verification link; attached files are shown only if they are public images.') }}</p>
    </div>

    <x-flash />

    <div class="grid gap-6 lg:grid-cols-[24rem_1fr]">
        <form wire:submit="save" class="card h-fit space-y-4" novalidate>
            <h2 class="text-base font-semibold">{{ $editingId ? __('Edit certification') : __('New certification') }}</h2>

            <x-form.input name="name" :label="__('Name')" maxlength="160" required />
            <x-form.input name="issuer" :label="__('Issuer')" maxlength="160" required />
            <div class="grid grid-cols-2 gap-3">
                <x-form.input name="issue_date" type="date" :label="__('Issued')" required />
                <x-form.input name="expiry_date" type="date" :label="__('Expires')" />
            </div>
            <x-form.input name="credential_id" :label="__('Credential ID')" maxlength="120" />
            <x-form.input name="credential_url" type="url" :label="__('Verification URL')" maxlength="500" placeholder="https://" />
            <x-form.select name="file_media_id" :label="__('Certificate file')" :hint="__('Optional. Upload it in the media library first.')">
                <option value="">{{ __('No file') }}</option>
                @foreach ($this->files as $file)
                    <option value="{{ $file->id }}" wire:key="file-{{ $file->id }}">{{ $file->original_filename }} ({{ $file->visibility->label() }})</option>
                @endforeach
            </x-form.select>
            <x-form.checkbox name="is_published" :label="__('Published')" />

            <div class="flex justify-end gap-2 border-t border-line pt-4">
                @if ($editingId)
                    <button type="button" wire:click="cancel" class="btn-secondary">{{ __('Cancel') }}</button>
                @endif
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Save') }}</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-line bg-surface">
            @if ($this->certifications->isEmpty())
                <p class="p-6 text-sm text-ink-muted">{{ __('No certifications yet.') }}</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Certification') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Issued') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->certifications as $certification)
                            <tr wire:key="certification-{{ $certification->id }}">
                                <td class="px-4 py-2.5">
                                    <span class="font-medium">{{ $certification->name }}</span>
                                    @unless ($certification->is_published)
                                        <x-badge class="ms-1">{{ __('Draft') }}</x-badge>
                                    @endunless
                                    @if ($certification->isExpired())
                                        <x-badge class="ms-1">{{ __('Expired') }}</x-badge>
                                    @endif
                                    <span class="block text-xs text-ink-muted">{{ $certification->issuer }}</span>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-ink-muted">{{ $certification->issue_date->format('M Y') }}</td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <button type="button" wire:click="edit({{ $certification->id }})" class="text-accent hover:underline">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="delete({{ $certification->id }})" wire:confirm="{{ __('Delete this certification?') }}" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
