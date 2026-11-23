<?php

use App\Models\Project;
use App\Services\QrCodeGenerator;
use App\Support\QrTarget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('QR codes')] class extends Component
{
    public string $target = 'home';

    public ?int $project_id = null;

    public bool $track = true;

    public function mount(): void
    {
        abort_unless(Gate::allows('access-admin'), 403);
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::query()->published()->ordered()->get(['id', 'title', 'slug']);
    }

    #[Computed]
    public function url(): ?string
    {
        $this->validate([
            'target' => ['required', Rule::in(array_keys(QrTarget::options()))],
            'project_id' => ['nullable', 'integer'],
        ]);

        return QrTarget::url($this->target, $this->project_id, $this->track);
    }

    /**
     * SVG preview as a data URI (allowed by the admin CSP's img-src data:).
     */
    #[Computed]
    public function preview(): ?string
    {
        return $this->url ? 'data:image/svg+xml;base64,'.base64_encode(app(QrCodeGenerator::class)->svg($this->url)) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function downloadQuery(string $format): array
    {
        return array_filter([
            'target' => $this->target,
            'project' => $this->target === 'project' ? $this->project_id : null,
            'format' => $format,
            'track' => $this->track ? 1 : 0,
        ], fn ($value) => $value !== null);
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('QR codes') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Print a QR code on your CV or add it to a digital business card. It always points to one of your public pages.') }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="card space-y-5">
            <x-form.select name="target" :label="__('Link to')" live>
                @foreach (\App\Support\QrTarget::options() as $value => $label)
                    <option value="{{ $value }}" wire:key="target-{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-form.select>

            @if ($target === 'project')
                <x-form.select name="project_id" :label="__('Project')" live>
                    <option value="">{{ __('Choose a published project') }}</option>
                    @foreach ($this->projects as $project)
                        <option value="{{ $project->id }}" wire:key="qr-project-{{ $project->id }}">{{ $project->title }}</option>
                    @endforeach
                </x-form.select>
            @endif

            <div>
                <x-form.checkbox name="track" :label="__('Count scans in Analytics')" live />
                <p class="form-hint">{{ __('Adds ?ref=qr to the link so visits from this code show up as “qr” in Analytics. No personal data is collected.') }}</p>
            </div>

            @if ($this->url)
                <div>
                    <p class="form-label">{{ __('Encoded link') }}</p>
                    <p class="rounded-md border border-line bg-paper px-3 py-2 font-mono text-xs break-all">{{ $this->url }}</p>
                </div>
            @endif
        </div>

        <aside class="card flex flex-col items-center gap-4" aria-labelledby="qr-preview-heading">
            <h2 id="qr-preview-heading" class="self-start text-base font-semibold">{{ __('Preview') }}</h2>
            @if ($this->preview)
                {{-- White tile in both themes: QR codes scan best as dark modules on white. --}}
                <img src="{{ $this->preview }}" alt="{{ __('QR code linking to :url', ['url' => $this->url]) }}" class="w-56 rounded-md bg-white p-2 ring-1 ring-line">
                <div class="flex w-full flex-col gap-2">
                    <a href="{{ route('admin.qr-codes.download', $this->downloadQuery('svg')) }}" class="btn-primary">{{ __('Download SVG (print)') }}</a>
                    <a href="{{ route('admin.qr-codes.download', $this->downloadQuery('png')) }}" class="btn-secondary">{{ __('Download PNG') }}</a>
                </div>
                <p class="text-xs text-ink-muted">{{ __('Tip: keep it at least 2 × 2 cm when printed, with white space around it.') }}</p>
            @else
                <p class="text-sm text-ink-muted">{{ __('Choose a published project to generate its QR code.') }}</p>
            @endif
        </aside>
    </div>
</div>
