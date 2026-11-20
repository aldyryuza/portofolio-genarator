<?php

use App\Actions\Media\StoreUploadedMedia;
use App\Enums\MediaVisibility;
use App\Models\ActivityLog;
use App\Models\MediaAsset;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('CV versions')] class extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $editingId = null;

    public string $title = 'Curriculum Vitae';

    public string $language = 'en';

    public string $version = '';

    public ?int $file_media_id = null;

    public $upload = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Resume::class);
        $this->version = now()->format('Y-m');
    }

    /**
     * @return Collection<int, Resume>
     */
    #[Computed]
    public function resumes(): Collection
    {
        return Resume::query()->with('file')->orderByDesc('is_default')->latest('id')->get();
    }

    /**
     * @return Collection<int, MediaAsset>
     */
    #[Computed]
    public function documents(): Collection
    {
        return MediaAsset::query()->documents()->latest('id')->get();
    }

    public function edit(int $id): void
    {
        $resume = Resume::findOrFail($id);
        $this->authorize('update', $resume);

        $this->resetValidation();
        $this->editingId = $resume->id;
        $this->fill($resume->only(['title', 'language', 'version', 'file_media_id']));
        $this->upload = null;
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->reset();
        $this->version = now()->format('Y-m');
    }

    public function save(StoreUploadedMedia $storeMedia): void
    {
        $resume = $this->editingId ? Resume::findOrFail($this->editingId) : new Resume;
        $this->authorize($resume->exists ? 'update' : 'create', $resume->exists ? $resume : Resume::class);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'language' => ['required', 'string', 'regex:/^[a-z]{2}(-[A-Z]{2})?$/'],
            'version' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique(Resume::class)->where('language', $this->language)->ignore($resume->id)],
            // Either a new PDF upload or an existing PDF from the library.
            'upload' => ['nullable', 'file', 'mimetypes:application/pdf'],
            'file_media_id' => [Rule::requiredIf($this->upload === null), 'nullable', 'integer',
                Rule::exists(MediaAsset::class, 'id')->whereIn('mime_type', MediaAsset::DOCUMENT_MIME_TYPES)->whereNull('deleted_at')],
        ], [
            'language.regex' => __('Use a language code such as en or id.'),
            'version.unique' => __('This version already exists for that language.'),
            'file_media_id.required' => __('Upload a PDF or choose one from the library.'),
        ]);

        /** @var User $user */
        $user = Auth::user();

        DB::transaction(function () use ($resume, $validated, $storeMedia, $user): void {
            $fileId = $validated['file_media_id'];

            if ($this->upload !== null) {
                $this->authorize('create', MediaAsset::class);
                // CV files stay private in the library; downloads are gated by the CV itself.
                $fileId = $storeMedia->handle($this->upload, 'upload', [], MediaVisibility::Private, $user)->id;
            }

            $resume->fill([...collect($validated)->only(['title', 'language', 'version'])->all(), 'file_media_id' => $fileId]);
            $isNew = ! $resume->exists;
            $changed = array_keys($resume->getDirty());
            $resume->save();

            ActivityLog::record($user, $isNew ? 'resume.created' : 'resume.updated', $resume, $isNew ? [] : ['changed' => $changed]);
        });

        $this->cancel();
        session()->flash('status', __('CV saved. Publish it to make it downloadable.'));
    }

    public function togglePublished(int $id): void
    {
        $resume = Resume::findOrFail($id);
        $this->authorize('update', $resume);

        $resume->is_public ? $resume->unpublish() : $resume->publish();
        $resume->save();

        ActivityLog::record(Auth::user(), $resume->is_public ? 'resume.published' : 'resume.unpublished', $resume);
    }

    public function makeDefault(int $id): void
    {
        $resume = Resume::findOrFail($id);
        $this->authorize('update', $resume);

        $resume->makeDefault();
        ActivityLog::record(Auth::user(), 'resume.made_default', $resume);
    }

    public function delete(int $id): void
    {
        $resume = Resume::findOrFail($id);
        $this->authorize('delete', $resume);

        // Only the CV entry is removed; the PDF stays in the media library.
        $resume->delete();
        ActivityLog::record(Auth::user(), 'resume.deleted', $resume, ['version' => $resume->version]);

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('status', __('CV version deleted.'));
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('CV versions') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Visitors can only download published versions. The default version is offered first on the CV page.') }}</p>
    </div>

    <x-flash />

    <div class="grid gap-6 lg:grid-cols-[24rem_1fr]">
        <form wire:submit="save" class="card h-fit space-y-4" novalidate>
            <h2 class="text-base font-semibold">{{ $editingId ? __('Edit version') : __('New version') }}</h2>

            <x-form.input name="title" :label="__('Title')" maxlength="120" required />
            <div class="grid grid-cols-2 gap-3">
                <x-form.input name="language" :label="__('Language')" maxlength="5" :hint="__('e.g. en, id')" required />
                <x-form.input name="version" :label="__('Version')" maxlength="30" :hint="__('e.g. 2026-11')" required />
            </div>

            <div>
                <label for="upload" class="form-label">{{ $editingId ? __('Replace with a new PDF') : __('Upload PDF') }}</label>
                <input wire:model="upload" id="upload" type="file" accept=".pdf,application/pdf"
                    class="block w-full text-sm text-ink-muted file:me-3 file:rounded-md file:border-0 file:bg-paper file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink"
                    @error('upload') aria-invalid="true" aria-describedby="upload-error" @enderror>
                <p wire:loading wire:target="upload" class="form-hint">{{ __('Uploading…') }}</p>
                @error('upload') <p id="upload-error" class="form-error">{{ $message }}</p> @enderror
            </div>

            <x-form.select name="file_media_id" :label="__('…or use a PDF from the library')">
                <option value="">{{ __('Choose a PDF') }}</option>
                @foreach ($this->documents as $document)
                    <option value="{{ $document->id }}" wire:key="document-{{ $document->id }}">{{ $document->original_filename }}</option>
                @endforeach
            </x-form.select>

            <div class="flex justify-end gap-2 border-t border-line pt-4">
                @if ($editingId)
                    <button type="button" wire:click="cancel" class="btn-secondary">{{ __('Cancel') }}</button>
                @endif
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="upload, save">{{ __('Save') }}</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-line bg-surface">
            @if ($this->resumes->isEmpty())
                <p class="p-6 text-sm text-ink-muted">{{ __('No CV versions yet.') }}</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Version') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Status') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->resumes as $resume)
                            <tr wire:key="resume-{{ $resume->id }}">
                                <td class="px-4 py-2.5">
                                    <span class="font-medium">{{ $resume->title }}</span>
                                    <span class="block text-xs text-ink-muted">{{ strtoupper($resume->language) }} · {{ $resume->version }} · {{ $resume->file->original_filename }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <x-badge :tone="$resume->is_public ? 'success' : 'neutral'">{{ $resume->is_public ? __('Published') : __('Private') }}</x-badge>
                                    @if ($resume->is_default) <x-badge tone="accent">{{ __('Default') }}</x-badge> @endif
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <a href="{{ route('cv.download', $resume) }}" class="text-accent hover:underline">{{ __('Download') }}</a>
                                    <button type="button" wire:click="togglePublished({{ $resume->id }})" class="ms-3 text-accent hover:underline">{{ $resume->is_public ? __('Unpublish') : __('Publish') }}</button>
                                    @unless ($resume->is_default)
                                        <button type="button" wire:click="makeDefault({{ $resume->id }})" class="ms-3 text-accent hover:underline">{{ __('Make default') }}</button>
                                    @endunless
                                    <button type="button" wire:click="edit({{ $resume->id }})" class="ms-3 text-accent hover:underline">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="delete({{ $resume->id }})" wire:confirm="{{ __('Delete this CV version? The PDF stays in the media library.') }}" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
