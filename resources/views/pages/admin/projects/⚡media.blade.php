<?php

use App\Actions\Media\StoreUploadedMedia;
use App\Enums\MediaVisibility;
use App\Models\ActivityLog;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\ProjectMedia;
use App\Models\ProjectVideo;
use App\Models\User;
use App\Support\SortOrder;
use App\Support\VideoUrl;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Project media')] class extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $projectId;

    /** @var array<int|string, string|null> */
    public array $captions = [];

    public $upload = null;

    public ?string $upload_alt_text = null;

    public string $upload_visibility = 'private';

    public string $video_url = '';

    public ?string $video_title = null;

    public function mount(Project $project): void
    {
        $this->authorize('update', $project);

        $this->projectId = $project->id;
        $this->syncCaptions();
    }

    #[Computed]
    public function project(): Project
    {
        return Project::with(['cover', 'mediaItems.media', 'videos'])->findOrFail($this->projectId);
    }

    /**
     * @return Collection<int, MediaAsset>
     */
    #[Computed]
    public function availableImages(): Collection
    {
        return MediaAsset::query()
            ->images()
            ->whereNotIn('id', ProjectMedia::where('project_id', $this->projectId)->select('media_id'))
            ->latest('id')
            ->limit(48)
            ->get();
    }

    public function uploadToGallery(StoreUploadedMedia $storeMedia): void
    {
        $this->authorizeProject();

        $validated = $this->validate([
            'upload_alt_text' => [Rule::requiredIf($this->upload_visibility === MediaVisibility::Public->value), 'nullable', 'string', 'max:255'],
            'upload_visibility' => ['required', Rule::enum(MediaVisibility::class)],
        ], ['upload_alt_text.required' => __('Public images need alt text.')]);

        // The gallery holds images only; PDFs are rejected before storing.
        $this->validate(['upload' => ['required', 'file', 'mimetypes:'.implode(',', MediaAsset::IMAGE_MIME_TYPES)]]);

        $this->authorize('create', MediaAsset::class);

        /** @var User $user */
        $user = Auth::user();
        $media = $storeMedia->handle($this->upload, 'upload', ['alt_text' => $validated['upload_alt_text']], MediaVisibility::from($validated['upload_visibility']), $user);

        $this->attach($media->id);
        $this->reset('upload', 'upload_alt_text', 'upload_visibility');
    }

    public function attach(int $mediaId): void
    {
        $project = $this->authorizeProject();
        $media = MediaAsset::images()->findOrFail($mediaId);

        // Created through the relation so project_id (not fillable) is set by Eloquent.
        $project->mediaItems()->firstOrCreate(
            ['media_id' => $media->id],
            ['role' => 'gallery', 'sort_order' => (int) ProjectMedia::where('project_id', $project->id)->max('sort_order') + 1],
        );

        $this->logChange('gallery.added', ['media' => $media->uuid]);
        $this->syncCaptions();
    }

    public function detach(int $itemId): void
    {
        $this->authorizeProject();
        $item = $this->item($itemId);

        $item->delete();

        $this->logChange('gallery.removed', ['media_id' => $item->media_id]);
        $this->syncCaptions();
    }

    public function moveItem(int $itemId, string $direction): void
    {
        $this->authorizeProject();

        if (in_array($direction, ['up', 'down'], true)) {
            $siblings = ProjectMedia::where('project_id', $this->projectId)->orderBy('sort_order')->orderBy('id');
            SortOrder::move($siblings, $this->item($itemId), $direction);
            $this->logChange('gallery.reordered');
        }
    }

    public function saveCaption(int $itemId): void
    {
        $this->authorizeProject();
        $item = $this->item($itemId);

        $this->validate(["captions.{$itemId}" => ['nullable', 'string', 'max:500']]);

        $caption = trim((string) ($this->captions[$itemId] ?? ''));
        $item->update(['caption' => $caption === '' ? null : $caption]);

        $this->logChange('gallery.caption_updated');
        session()->flash('status', __('Caption saved.'));
    }

    public function setCover(int $mediaId): void
    {
        $project = $this->authorizeProject();
        $media = MediaAsset::images()->findOrFail($mediaId);

        $project->forceFill(['cover_media_id' => $media->id])->save();
        $this->logChange('cover.set', ['media' => $media->uuid]);
    }

    public function clearCover(): void
    {
        $project = $this->authorizeProject();

        $project->forceFill(['cover_media_id' => null])->save();
        $this->logChange('cover.cleared');
    }

    public function makePublic(int $mediaId): void
    {
        $this->authorizeProject();
        $media = MediaAsset::findOrFail($mediaId);
        $this->authorize('update', $media);

        if (blank($media->alt_text)) {
            $this->addError('media', __('Add alt text in the media library before making this image public.'));

            return;
        }

        $media->update(['visibility' => MediaVisibility::Public]);
        ActivityLog::record(Auth::user(), 'media.updated', $media, ['changed' => ['visibility']]);
    }

    public function addVideo(): void
    {
        $project = $this->authorizeProject();

        $this->validate([
            'video_url' => ['required', 'string', 'max:500'],
            'video_title' => ['nullable', 'string', 'max:160'],
        ]);

        $parsed = VideoUrl::parse($this->video_url);

        if ($parsed === null) {
            $this->addError('video_url', __('Paste a YouTube or Vimeo link.'));

            return;
        }

        $exists = ProjectVideo::where('project_id', $project->id)
            ->where('provider', $parsed['provider'])
            ->where('video_id', $parsed['id'])
            ->exists();

        if ($exists) {
            $this->addError('video_url', __('This video is already attached.'));

            return;
        }

        $project->videos()->create([
            'provider' => $parsed['provider'],
            'video_id' => $parsed['id'],
            'title' => filled($this->video_title) ? trim($this->video_title) : null,
            'sort_order' => (int) ProjectVideo::where('project_id', $project->id)->max('sort_order') + 1,
        ]);

        $this->logChange('video.added', ['provider' => $parsed['provider']->value, 'video_id' => $parsed['id']]);
        $this->reset('video_url', 'video_title');
    }

    public function removeVideo(int $videoId): void
    {
        $this->authorizeProject();

        ProjectVideo::where('project_id', $this->projectId)->findOrFail($videoId)->delete();
        $this->logChange('video.removed');
    }

    public function moveVideo(int $videoId, string $direction): void
    {
        $this->authorizeProject();

        if (in_array($direction, ['up', 'down'], true)) {
            $siblings = ProjectVideo::where('project_id', $this->projectId)->orderBy('sort_order')->orderBy('id');
            SortOrder::move($siblings, ProjectVideo::where('project_id', $this->projectId)->findOrFail($videoId), $direction);
            $this->logChange('video.reordered');
        }
    }

    private function authorizeProject(): Project
    {
        $project = Project::findOrFail($this->projectId);
        $this->authorize('update', $project);
        unset($this->project, $this->availableImages);

        return $project;
    }

    /**
     * Gallery entries are always looked up within this project.
     */
    private function item(int $itemId): ProjectMedia
    {
        return ProjectMedia::where('project_id', $this->projectId)->findOrFail($itemId);
    }

    private function syncCaptions(): void
    {
        $this->captions = ProjectMedia::where('project_id', $this->projectId)->pluck('caption', 'id')->all();
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function logChange(string $change, array $details = []): void
    {
        ActivityLog::record(Auth::user(), 'project.media_updated', Project::find($this->projectId), ['change' => $change, ...$details]);
    }
};
?>

<div class="space-y-6">
    <div>
        <a href="{{ route('admin.projects.edit', $this->project) }}" wire:navigate class="text-sm text-ink-muted hover:text-ink">← {{ __('Back to project') }}</a>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Media for “:title”', ['title' => $this->project->title]) }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Only public images and videos appear on the public project page.') }}</p>
    </div>

    <x-flash />
    @error('media') <p class="form-error" role="alert">{{ $message }}</p> @enderror

    <section class="card" aria-labelledby="cover-heading">
        <h2 id="cover-heading" class="text-base font-semibold">{{ __('Cover image') }}</h2>
        @if ($cover = $this->project->cover)
            <div class="mt-3 flex flex-wrap items-start gap-4">
                <img src="{{ $cover->url('thumb') }}" alt="{{ $cover->alt_text ?? '' }}" class="w-48 rounded-md border border-line object-cover">
                <div class="space-y-2 text-sm">
                    <x-badge :tone="$cover->isPublic() ? 'success' : 'neutral'">{{ $cover->visibility->label() }}</x-badge>
                    @unless ($cover->isPublic())
                        <button type="button" wire:click="makePublic({{ $cover->id }})" class="block text-accent hover:underline">{{ __('Make public') }}</button>
                    @endunless
                    <button type="button" wire:click="clearCover" class="block text-danger hover:underline">{{ __('Remove cover') }}</button>
                </div>
            </div>
        @else
            <p class="mt-2 text-sm text-ink-muted">{{ __('No cover yet. Use “Set as cover” on a gallery image.') }}</p>
        @endif
    </section>

    <section class="space-y-3" aria-labelledby="gallery-heading">
        <h2 id="gallery-heading" class="text-lg font-semibold">{{ __('Gallery') }}</h2>

        @if ($this->project->mediaItems->isEmpty())
            <p class="card text-sm text-ink-muted">{{ __('No gallery images yet.') }}</p>
        @else
            <ol class="space-y-3">
                @foreach ($this->project->mediaItems as $item)
                    <li wire:key="item-{{ $item->id }}" class="card flex flex-col gap-4 sm:flex-row sm:items-start">
                        <img src="{{ $item->media->url('thumb') }}" alt="{{ $item->media->alt_text ?? '' }}" loading="lazy" class="w-full rounded-md border border-line object-cover sm:w-40">
                        <div class="min-w-0 flex-1 space-y-2">
                            <p class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="truncate font-medium">{{ $item->media->original_filename }}</span>
                                <x-badge :tone="$item->media->isPublic() ? 'success' : 'neutral'">{{ $item->media->visibility->label() }}</x-badge>
                                @if ($this->project->cover_media_id === $item->media_id)
                                    <x-badge tone="accent">{{ __('Cover') }}</x-badge>
                                @endif
                            </p>
                            <form wire:submit="saveCaption({{ $item->id }})" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <div class="flex-1">
                                    <label for="caption-{{ $item->id }}" class="form-label">{{ __('Caption in this project') }}</label>
                                    <input wire:model="captions.{{ $item->id }}" id="caption-{{ $item->id }}" type="text" maxlength="500" class="form-control">
                                    @error("captions.{$item->id}") <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <button type="submit" class="btn-secondary">{{ __('Save caption') }}</button>
                            </form>
                            <p class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                <button type="button" wire:click="moveItem({{ $item->id }}, 'up')" class="text-ink-muted hover:text-ink" aria-label="{{ __('Move image up') }}">↑ {{ __('Up') }}</button>
                                <button type="button" wire:click="moveItem({{ $item->id }}, 'down')" class="text-ink-muted hover:text-ink" aria-label="{{ __('Move image down') }}">↓ {{ __('Down') }}</button>
                                @if ($this->project->cover_media_id !== $item->media_id)
                                    <button type="button" wire:click="setCover({{ $item->media_id }})" class="text-accent hover:underline">{{ __('Set as cover') }}</button>
                                @endif
                                @unless ($item->media->isPublic())
                                    <button type="button" wire:click="makePublic({{ $item->media_id }})" class="text-accent hover:underline">{{ __('Make public') }}</button>
                                @endunless
                                <button type="button" wire:click="detach({{ $item->id }})" wire:confirm="{{ __('Remove this image from the gallery? The file stays in the media library.') }}" class="text-danger hover:underline">{{ __('Remove') }}</button>
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <form wire:submit="uploadToGallery" class="card space-y-4" novalidate>
            <h2 class="text-base font-semibold">{{ __('Upload a new image') }}</h2>
            <div>
                <label for="upload" class="form-label">{{ __('Image') }}</label>
                <input wire:model="upload" id="upload" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    class="block w-full text-sm text-ink-muted file:me-3 file:rounded-md file:border-0 file:bg-paper file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink"
                    @error('upload') aria-invalid="true" aria-describedby="upload-error" @enderror>
                <p wire:loading wire:target="upload" class="form-hint">{{ __('Uploading…') }}</p>
                @error('upload') <p id="upload-error" class="form-error">{{ $message }}</p> @enderror
            </div>
            <x-form.input name="upload_alt_text" :label="__('Alt text')" maxlength="255" />
            <x-form.select name="upload_visibility" :label="__('Visibility')">
                @foreach (\App\Enums\MediaVisibility::cases() as $option)
                    <option value="{{ $option->value }}" wire:key="upload-visibility-{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </x-form.select>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="upload, uploadToGallery">{{ __('Upload and add') }}</button>
            </div>
        </form>

        <section class="card space-y-3" aria-labelledby="library-heading">
            <h2 id="library-heading" class="text-base font-semibold">{{ __('Add from library') }}</h2>
            @if ($this->availableImages->isEmpty())
                <p class="text-sm text-ink-muted">{{ __('No other images in the library.') }}</p>
            @else
                <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    @foreach ($this->availableImages as $image)
                        <li wire:key="available-{{ $image->id }}">
                            <button type="button" wire:click="attach({{ $image->id }})" class="group relative block w-full overflow-hidden rounded-md border border-line focus-visible:outline-2 focus-visible:outline-accent">
                                <img src="{{ $image->url('thumb') }}" alt="" loading="lazy" class="aspect-square w-full object-cover group-hover:opacity-80">
                                <span class="sr-only">{{ __('Add :name to gallery', ['name' => $image->alt_text ?: $image->original_filename]) }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <section class="space-y-3" aria-labelledby="videos-heading">
        <h2 id="videos-heading" class="text-lg font-semibold">{{ __('Videos') }}</h2>

        <form wire:submit="addVideo" class="card grid gap-4 sm:grid-cols-[1fr_16rem_auto] sm:items-end" novalidate>
            <x-form.input name="video_url" type="url" :label="__('YouTube or Vimeo link')" maxlength="500" placeholder="https://www.youtube.com/watch?v=…" />
            <x-form.input name="video_title" :label="__('Title (optional)')" maxlength="160" />
            <button type="submit" class="btn-primary">{{ __('Add video') }}</button>
        </form>

        @if ($this->project->videos->isNotEmpty())
            <ol class="divide-y divide-line rounded-lg border border-line bg-surface">
                @foreach ($this->project->videos as $video)
                    <li wire:key="video-{{ $video->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <span class="font-medium">{{ $video->title ?? __('Untitled video') }}</span>
                            <span class="block text-xs text-ink-muted">{{ $video->provider->label() }} · <a href="{{ $video->watchUrl() }}" target="_blank" rel="noopener noreferrer" class="font-mono hover:underline">{{ $video->video_id }}</a></span>
                        </div>
                        <p class="flex gap-x-4">
                            <button type="button" wire:click="moveVideo({{ $video->id }}, 'up')" class="text-ink-muted hover:text-ink" aria-label="{{ __('Move video up') }}">↑</button>
                            <button type="button" wire:click="moveVideo({{ $video->id }}, 'down')" class="text-ink-muted hover:text-ink" aria-label="{{ __('Move video down') }}">↓</button>
                            <button type="button" wire:click="removeVideo({{ $video->id }})" wire:confirm="{{ __('Remove this video?') }}" class="text-danger hover:underline">{{ __('Remove') }}</button>
                        </p>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>
