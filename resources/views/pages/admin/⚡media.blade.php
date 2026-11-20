<?php

use App\Actions\Media\StoreUploadedMedia;
use App\Enums\MediaVisibility;
use App\Models\ActivityLog;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Media library')] class extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(except: '')]
    public string $type = '';

    public $upload = null;

    public ?string $alt_text = null;

    public ?string $caption = null;

    public ?string $credit = null;

    public string $visibility = 'private';

    #[Locked]
    public ?int $editingId = null;

    public ?string $edit_alt_text = null;

    public ?string $edit_caption = null;

    public ?string $edit_credit = null;

    public string $edit_visibility = 'private';

    public function mount(): void
    {
        $this->authorize('viewAny', MediaAsset::class);
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, MediaAsset>
     */
    #[Computed]
    public function assets(): LengthAwarePaginator
    {
        return MediaAsset::query()
            ->when($this->type === 'image', fn ($query) => $query->images())
            ->when($this->type === 'document', fn ($query) => $query->documents())
            ->latest('id')
            ->paginate(24);
    }

    public function save(StoreUploadedMedia $storeMedia): void
    {
        $isPublicImage = fn (): bool => $this->visibility === MediaVisibility::Public->value
            && $this->upload instanceof UploadedFile
            && in_array($this->upload->getMimeType(), MediaAsset::IMAGE_MIME_TYPES, true);

        $meta = $this->validate([
            'alt_text' => [Rule::requiredIf($isPublicImage), 'nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
            'credit' => ['nullable', 'string', 'max:255'],
            'visibility' => ['required', Rule::enum(MediaVisibility::class)],
        ], ['alt_text.required' => __('Public images need alt text.')]);

        $this->authorize('create', MediaAsset::class);

        /** @var User $user */
        $user = Auth::user();
        $storeMedia->handle($this->upload, 'upload', $meta, MediaVisibility::from($meta['visibility']), $user);

        $this->reset('upload', 'alt_text', 'caption', 'credit', 'visibility');
        $this->resetPage();
        session()->flash('status', __('File uploaded.'));
    }

    public function edit(int $id): void
    {
        $media = MediaAsset::findOrFail($id);
        $this->authorize('update', $media);

        $this->resetValidation();
        $this->editingId = $media->id;
        $this->edit_alt_text = $media->alt_text;
        $this->edit_caption = $media->caption;
        $this->edit_credit = $media->credit;
        $this->edit_visibility = $media->visibility->value;
    }

    public function cancelEdit(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'edit_alt_text', 'edit_caption', 'edit_credit', 'edit_visibility');
    }

    public function update(): void
    {
        $media = MediaAsset::findOrFail($this->editingId);
        $this->authorize('update', $media);

        $validated = $this->validate([
            'edit_alt_text' => [Rule::requiredIf($media->isImage() && $this->edit_visibility === 'public'), 'nullable', 'string', 'max:255'],
            'edit_caption' => ['nullable', 'string', 'max:500'],
            'edit_credit' => ['nullable', 'string', 'max:255'],
            'edit_visibility' => ['required', Rule::enum(MediaVisibility::class)],
        ], ['edit_alt_text.required' => __('Public images need alt text.')]);

        $media->fill([
            'alt_text' => $validated['edit_alt_text'],
            'caption' => $validated['edit_caption'],
            'credit' => $validated['edit_credit'],
            'visibility' => $validated['edit_visibility'],
        ]);
        $changed = array_keys($media->getDirty());
        $media->save();

        if ($changed !== []) {
            ActivityLog::record(Auth::user(), 'media.updated', $media, ['changed' => $changed]);
        }

        $this->cancelEdit();
        session()->flash('status', __('Media updated.'));
    }

    public function delete(int $id): void
    {
        $media = MediaAsset::findOrFail($id);
        $this->authorize('delete', $media);

        if ($media->isReferenced()) {
            $this->addError('delete', __('This file is still used by a project or the profile. Remove it there first.'));

            return;
        }

        // Soft delete: files stay on disk until a future clean-up job removes them.
        $media->delete();
        ActivityLog::record(Auth::user(), 'media.deleted', $media);

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }

        session()->flash('status', __('Media deleted.'));
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Media library') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">
            {{ __('Images (JPG, PNG, WebP up to :image) and PDF documents (up to :pdf). Private files are never shown to visitors.', [
                'image' => Number::fileSize(config('portfolio.media.image.max_kb') * 1024),
                'pdf' => Number::fileSize(config('portfolio.media.document.max_kb') * 1024),
            ]) }}
        </p>
    </div>

    <x-flash />
    @error('delete') <p class="form-error" role="alert">{{ $message }}</p> @enderror

    <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
        <div class="space-y-6">
            <form wire:submit="save" class="card space-y-4" novalidate>
                <h2 class="text-base font-semibold">{{ __('Upload') }}</h2>

                <div>
                    <label for="upload" class="form-label">{{ __('File') }}</label>
                    <input wire:model="upload" id="upload" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
                        class="block w-full text-sm text-ink-muted file:me-3 file:rounded-md file:border-0 file:bg-paper file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink"
                        @error('upload') aria-invalid="true" aria-describedby="upload-error" @enderror>
                    <p wire:loading wire:target="upload" class="form-hint">{{ __('Uploading…') }}</p>
                    @error('upload') <p id="upload-error" class="form-error">{{ $message }}</p> @enderror
                </div>

                <x-form.input name="alt_text" :label="__('Alt text')" maxlength="255" :hint="__('Describe the image for screen readers. Required before an image is made public.')" />
                <x-form.input name="caption" :label="__('Caption')" maxlength="500" />
                <x-form.input name="credit" :label="__('Credit')" maxlength="255" />
                <x-form.select name="visibility" :label="__('Visibility')">
                    @foreach (\App\Enums\MediaVisibility::cases() as $option)
                        <option value="{{ $option->value }}" wire:key="visibility-{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </x-form.select>

                <div class="flex justify-end border-t border-line pt-4">
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="upload, save">{{ __('Upload') }}</button>
                </div>
            </form>

            @if ($editingId)
                <form wire:submit="update" class="card space-y-4" novalidate>
                    <h2 class="text-base font-semibold">{{ __('Edit details') }}</h2>
                    <x-form.input name="edit_alt_text" :label="__('Alt text')" maxlength="255" />
                    <x-form.input name="edit_caption" :label="__('Caption')" maxlength="500" />
                    <x-form.input name="edit_credit" :label="__('Credit')" maxlength="255" />
                    <x-form.select name="edit_visibility" :label="__('Visibility')">
                        @foreach (\App\Enums\MediaVisibility::cases() as $option)
                            <option value="{{ $option->value }}" wire:key="edit-visibility-{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-form.select>
                    <div class="flex justify-end gap-2 border-t border-line pt-4">
                        <button type="button" wire:click="cancelEdit" class="btn-secondary">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn-primary">{{ __('Save') }}</button>
                    </div>
                </form>
            @endif
        </div>

        <div class="space-y-4">
            <div class="sm:w-48">
                <label for="type" class="form-label">{{ __('Show') }}</label>
                <select wire:model.live="type" id="type" class="form-control">
                    <option value="">{{ __('All files') }}</option>
                    <option value="image">{{ __('Images') }}</option>
                    <option value="document">{{ __('Documents') }}</option>
                </select>
            </div>

            @if ($this->assets->isEmpty())
                <p class="card text-sm text-ink-muted">{{ __('No files yet.') }}</p>
            @else
                <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach ($this->assets as $asset)
                        <li wire:key="asset-{{ $asset->id }}" @class(['overflow-hidden rounded-lg border bg-surface', 'border-accent ring-2 ring-accent/30' => $editingId === $asset->id, 'border-line' => $editingId !== $asset->id])>
                            <div class="flex aspect-[4/3] items-center justify-center bg-paper">
                                @if ($asset->isImage())
                                    <img src="{{ $asset->url('thumb') }}" alt="{{ $asset->alt_text ?? '' }}" loading="lazy" class="size-full object-cover">
                                @else
                                    <span class="font-mono text-xs text-ink-muted">PDF</span>
                                @endif
                            </div>
                            <div class="space-y-1.5 p-3 text-xs">
                                <p class="truncate font-medium text-ink" title="{{ $asset->original_filename }}">{{ $asset->original_filename }}</p>
                                <p class="flex flex-wrap items-center gap-1.5 text-ink-muted">
                                    <x-badge :tone="$asset->isPublic() ? 'success' : 'neutral'">{{ $asset->visibility->label() }}</x-badge>
                                    {{ Number::fileSize($asset->bytes) }}
                                    @if ($asset->width) · {{ $asset->width }}×{{ $asset->height }} @endif
                                </p>
                                @if ($asset->isImage() && blank($asset->alt_text))
                                    <p class="text-danger">{{ __('Missing alt text') }}</p>
                                @endif
                                <p class="flex flex-wrap gap-x-3 pt-1">
                                    <button type="button" wire:click="edit({{ $asset->id }})" class="text-accent hover:underline">{{ __('Edit') }}</button>
                                    <a href="{{ $asset->url('original') }}" class="text-accent hover:underline" target="_blank" rel="noopener">{{ __('Original') }}</a>
                                    <button type="button" wire:click="delete({{ $asset->id }})" wire:confirm="{{ __('Delete this file?') }}" class="text-danger hover:underline">{{ __('Delete') }}</button>
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{ $this->assets->links() }}
            @endif
        </div>
    </div>
</div>
