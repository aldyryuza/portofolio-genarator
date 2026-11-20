<?php

use App\Actions\Exports\GeneratePortfolioExport;
use App\Enums\ExportFormat;
use App\Enums\ExportState;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\ExportJob;
use App\Models\ExportPreset;
use App\Models\Project;
use App\Models\User;
use App\Services\Export\ExportOptions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Exports')] class extends Component
{
    private const MAX_EXPORTS_PER_MINUTE = 6;

    public string $format = 'pdf';

    public string $scope = 'all';

    /** @var array<int, int|string> */
    public array $project_ids = [];

    public ?int $category_id = null;

    public bool $include_drafts = false;

    public bool $include_profile = true;

    public bool $include_experience = true;

    public bool $include_education = true;

    public bool $include_gallery = true;

    public int $max_projects = 12;

    public string $template = 'light';

    public string $locale = 'en';

    public string $preset_name = '';

    public ?int $presetId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ExportJob::class);
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::query()->ordered()->get(['id', 'title', 'status', 'published_at']);
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->ordered()->get();
    }

    /**
     * @return Collection<int, ExportPreset>
     */
    #[Computed]
    public function presets(): Collection
    {
        return ExportPreset::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, ExportJob>
     */
    #[Computed]
    public function jobs(): Collection
    {
        return ExportJob::query()->latest('id')->limit(15)->get();
    }

    public function generate(GeneratePortfolioExport $generate): void
    {
        $this->authorize('create', ExportJob::class);

        /** @var User $user */
        $user = Auth::user();
        $key = 'exports:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_EXPORTS_PER_MINUTE)) {
            $this->addError('format', __('Too many exports at once. Try again in a minute.'));

            return;
        }

        RateLimiter::hit($key);

        $job = $generate->handle(ExportOptions::fromArray($this->optionsInput()), $user, $this->presetId);
        unset($this->jobs);

        $job->state === ExportState::Completed
            ? session()->flash('status', __('Export ready. Download it from the list below.'))
            : $this->addError('format', __('The export failed: :reason', ['reason' => $job->failed_reason]));
    }

    public function savePreset(): void
    {
        $this->authorize('create', ExportPreset::class);
        $this->validate(['preset_name' => ['required', 'string', 'max:80']]);

        $options = ExportOptions::fromArray($this->optionsInput());

        $preset = new ExportPreset([
            'name' => $this->preset_name,
            'format' => $options->format,
            'configuration_json' => $options->toArray(),
            'template_key' => $options->template,
        ]);
        $preset->forceFill(['created_by' => Auth::id()])->save();

        ActivityLog::record(Auth::user(), 'export_preset.created', $preset);

        $this->presetId = $preset->id;
        $this->reset('preset_name');
        session()->flash('status', __('Preset saved.'));
    }

    public function loadPreset(int $id): void
    {
        $preset = ExportPreset::findOrFail($id);
        $this->authorize('view', $preset);

        $config = $preset->configuration_json;
        $this->presetId = $preset->id;
        $this->format = (string) ($config['format'] ?? 'pdf');
        $this->scope = (string) ($config['scope'] ?? 'all');
        $this->project_ids = array_values(array_map('intval', (array) ($config['project_ids'] ?? [])));
        $this->category_id = isset($config['category_id']) ? (int) $config['category_id'] : null;
        $this->max_projects = (int) ($config['max_projects'] ?? 12);
        $this->template = (string) ($config['template'] ?? 'light');
        $this->locale = (string) ($config['locale'] ?? 'en');

        foreach (['include_drafts', 'include_profile', 'include_experience', 'include_education', 'include_gallery'] as $flag) {
            $this->{$flag} = (bool) ($config[$flag] ?? $this->{$flag});
        }

        $this->resetValidation();
    }

    public function deletePreset(int $id): void
    {
        $preset = ExportPreset::findOrFail($id);
        $this->authorize('delete', $preset);

        $preset->delete();
        ActivityLog::record(Auth::user(), 'export_preset.deleted', $preset);

        if ($this->presetId === $id) {
            $this->presetId = null;
        }
    }

    public function deleteJob(int $id): void
    {
        $job = ExportJob::findOrFail($id);
        $this->authorize('delete', $job);

        if ($job->output_disk && $job->output_path) {
            Storage::disk($job->output_disk)->delete($job->output_path);
        }

        $job->delete();
        ActivityLog::record(Auth::user(), 'export.deleted', $job);
        unset($this->jobs);
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsInput(): array
    {
        return [
            'format' => $this->format,
            'scope' => $this->scope,
            'project_ids' => $this->scope === 'selected' ? $this->project_ids : [],
            'category_id' => $this->scope === 'category' ? $this->category_id : null,
            'include_drafts' => $this->include_drafts,
            'include_profile' => $this->include_profile,
            'include_experience' => $this->include_experience,
            'include_education' => $this->include_education,
            'include_gallery' => $this->include_gallery,
            'max_projects' => $this->max_projects,
            'template' => $this->template,
            'locale' => $this->locale,
        ];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Exports') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Generate a PDF portfolio or a PowerPoint deck. Only public images are included, and drafts only if you tick the option. Exported files are private to you.') }}</p>
    </div>

    <x-flash />

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <form wire:submit="generate" class="card space-y-6" novalidate>
            @error('format') <p class="form-error" role="alert">{{ $message }}</p> @enderror

            <fieldset class="space-y-3">
                <legend class="text-base font-semibold">{{ __('Format & look') }}</legend>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.select name="format" :label="__('Format')" live>
                        @foreach (\App\Enums\ExportFormat::cases() as $option)
                            <option value="{{ $option->value }}" wire:key="format-{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.select name="template" :label="__('Theme')">
                        <option value="light">{{ __('Light') }}</option>
                        <option value="dark">{{ __('Dark') }}</option>
                    </x-form.select>
                    <x-form.select name="locale" :label="__('Language')">
                        <option value="en">English</option>
                        <option value="id">Bahasa Indonesia</option>
                    </x-form.select>
                </div>
            </fieldset>

            <fieldset class="space-y-3">
                <legend class="text-base font-semibold">{{ __('Projects') }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.select name="scope" :label="__('Which projects')" live>
                        <option value="all">{{ __('All (in site order)') }}</option>
                        <option value="selected">{{ __('Selected projects') }}</option>
                        <option value="category">{{ __('One category') }}</option>
                    </x-form.select>
                    <x-form.input name="max_projects" type="number" :label="$format === 'pptx' ? __('Max projects (≈ slides)') : __('Max projects')" min="1" :max="\App\Services\Export\ExportOptions::MAX_PROJECTS" />
                </div>

                @if ($scope === 'category')
                    <x-form.select name="category_id" :label="__('Category')">
                        <option value="">{{ __('Choose a category') }}</option>
                        @foreach ($this->categories as $category)
                            <option value="{{ $category->id }}" wire:key="category-{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </x-form.select>
                @endif

                @if ($scope === 'selected')
                    <div>
                        <p class="form-label">{{ __('Projects to include') }}</p>
                        <div class="max-h-64 space-y-1.5 overflow-y-auto rounded-md border border-line p-3">
                            @forelse ($this->projects as $project)
                                <label wire:key="pick-{{ $project->id }}" class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model="project_ids" value="{{ $project->id }}" class="size-4 rounded border-line-strong accent-accent">
                                    {{ $project->title }}
                                    @unless ($project->isPublished()) <x-badge>{{ __('Draft') }}</x-badge> @endunless
                                </label>
                            @empty
                                <p class="text-sm text-ink-muted">{{ __('No projects yet.') }}</p>
                            @endforelse
                        </div>
                        @error('project_ids') <p class="form-error">{{ $message }}</p> @enderror
                        @error('project_ids.*') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <x-form.checkbox name="include_drafts" :label="__('Include unpublished projects (owner-only export)')" />
            </fieldset>

            <fieldset class="space-y-2">
                <legend class="text-base font-semibold">{{ __('Sections') }}</legend>
                <x-form.checkbox name="include_profile" :label="__('Profile & introduction')" />
                <x-form.checkbox name="include_gallery" :label="__('Project galleries')" />
                <x-form.checkbox name="include_experience" :label="__('Experience')" />
                <x-form.checkbox name="include_education" :label="__('Education & certifications')" />
            </fieldset>

            <div class="flex flex-wrap items-end justify-between gap-3 border-t border-line pt-5">
                <div class="flex items-end gap-2">
                    <div>
                        <label for="preset_name" class="form-label">{{ __('Save as preset') }}</label>
                        <input wire:model="preset_name" id="preset_name" type="text" maxlength="80" class="form-control" placeholder="{{ __('e.g. Client deck') }}">
                        @error('preset_name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="savePreset" class="btn-secondary">{{ __('Save') }}</button>
                </div>
                <button type="submit" class="btn-primary px-5" wire:loading.attr="disabled" wire:target="generate">
                    <span wire:loading.remove wire:target="generate">{{ __('Generate :format', ['format' => strtoupper($format)]) }}</span>
                    <span wire:loading wire:target="generate">{{ __('Generating…') }}</span>
                </button>
            </div>
        </form>

        <aside class="card h-fit space-y-3" aria-labelledby="presets-heading">
            <h2 id="presets-heading" class="text-base font-semibold">{{ __('Presets') }}</h2>
            @forelse ($this->presets as $preset)
                <div wire:key="preset-{{ $preset->id }}" @class(['flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm', 'bg-paper' => $presetId === $preset->id])>
                    <button type="button" wire:click="loadPreset({{ $preset->id }})" class="truncate text-left hover:underline">
                        {{ $preset->name }} <span class="text-xs text-ink-muted">{{ $preset->format->label() }}</span>
                    </button>
                    <button type="button" wire:click="deletePreset({{ $preset->id }})" wire:confirm="{{ __('Delete this preset?') }}" class="text-xs text-danger hover:underline">{{ __('Delete') }}</button>
                </div>
            @empty
                <p class="text-sm text-ink-muted">{{ __('No presets yet.') }}</p>
            @endforelse
        </aside>
    </div>

    <section aria-labelledby="history-heading">
        <h2 id="history-heading" class="text-lg font-semibold">{{ __('Recent exports') }}</h2>
        @if ($this->jobs->isEmpty())
            <p class="mt-3 text-sm text-ink-muted">{{ __('Nothing exported yet.') }}</p>
        @else
            <div class="mt-3 overflow-x-auto rounded-lg border border-line bg-surface">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Created') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Format') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Status') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->jobs as $job)
                            <tr wire:key="job-{{ $job->id }}">
                                <td class="px-4 py-2.5 whitespace-nowrap">{{ $job->created_at?->translatedFormat('j M Y, H:i') }}</td>
                                <td class="px-4 py-2.5">{{ $job->format->label() }}</td>
                                <td class="px-4 py-2.5">
                                    <x-badge :tone="$job->state === \App\Enums\ExportState::Completed ? 'success' : 'neutral'">{{ $job->state->label() }}</x-badge>
                                    @if ($job->output_bytes) <span class="ms-1 text-xs text-ink-muted">{{ \Illuminate\Support\Number::fileSize($job->output_bytes) }}</span> @endif
                                    @if ($job->failed_reason) <span class="block text-xs text-danger">{{ \Illuminate\Support\Str::limit($job->failed_reason, 120) }}</span> @endif
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    @if ($job->isReady())
                                        <a href="{{ route('admin.exports.download', $job) }}" class="text-accent hover:underline">{{ __('Download') }}</a>
                                    @endif
                                    <button type="button" wire:click="deleteJob({{ $job->id }})" wire:confirm="{{ __('Delete this export file?') }}" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
