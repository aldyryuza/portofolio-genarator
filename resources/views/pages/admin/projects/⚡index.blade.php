<?php

use App\Actions\Projects\MoveProject;
use App\Enums\ProjectStatus;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Projects')] class extends Component
{
    use WithPagination;

    private const FILTERS = ['', 'draft', 'published', 'trashed'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $filter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Project::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'filter'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    #[Computed]
    public function projects(): LengthAwarePaginator
    {
        $filter = in_array($this->filter, self::FILTERS, true) ? $this->filter : '';
        $search = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($this->search));

        return Project::query()
            ->with('category')
            ->when($filter === 'trashed', fn ($query) => $query->onlyTrashed())
            ->when(in_array($filter, ['draft', 'published'], true), fn ($query) => $query->where('status', $filter))
            ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->ordered()
            ->paginate(20);
    }

    public function togglePublished(int $id): void
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        DB::transaction(function () use ($project): void {
            $project->status === ProjectStatus::Published ? $project->unpublish() : $project->publish();
            $project->save();

            ActivityLog::record(Auth::user(), $project->status === ProjectStatus::Published ? 'project.published' : 'project.unpublished', $project);
        });
    }

    public function toggleFeatured(int $id): void
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        $project->update(['is_featured' => ! $project->is_featured]);
        ActivityLog::record(Auth::user(), 'project.updated', $project, ['changed' => ['is_featured']]);
    }

    public function move(int $id, string $direction): void
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        if (! in_array($direction, ['up', 'down'], true)) {
            return;
        }

        app(MoveProject::class)->handle($project, $direction);
        ActivityLog::record(Auth::user(), 'project.reordered', $project);
    }

    public function delete(int $id): void
    {
        $project = Project::findOrFail($id);
        $this->authorize('delete', $project);

        $project->delete();
        ActivityLog::record(Auth::user(), 'project.deleted', $project);

        session()->flash('status', __('Project moved to trash.'));
    }

    public function restore(int $id): void
    {
        $project = Project::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $project);

        $project->restore();
        ActivityLog::record(Auth::user(), 'project.restored', $project);

        session()->flash('status', __('Project restored.'));
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Projects') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ __('Drafts are never visible to visitors. Order here is the order on the public site.') }}</p>
        </div>
        <a href="{{ route('admin.projects.create') }}" wire:navigate class="btn-primary">{{ __('New project') }}</a>
    </div>

    <x-flash />

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="sm:w-72">
            <label for="search" class="form-label">{{ __('Search') }}</label>
            <input wire:model.live.debounce.300ms="search" id="search" type="search" class="form-control" placeholder="{{ __('Project title') }}">
        </div>
        <div class="sm:w-48">
            <label for="filter" class="form-label">{{ __('Show') }}</label>
            <select wire:model.live="filter" id="filter" class="form-control">
                <option value="">{{ __('All projects') }}</option>
                <option value="draft">{{ __('Drafts') }}</option>
                <option value="published">{{ __('Published') }}</option>
                <option value="trashed">{{ __('Trash') }}</option>
            </select>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-line bg-surface">
        @if ($this->projects->isEmpty())
            <p class="p-6 text-sm text-ink-muted">{{ __('No projects found.') }}</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line text-ink-muted">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Title') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Category') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Featured') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Order') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($this->projects as $project)
                        <tr wire:key="project-{{ $project->id }}">
                            <td class="px-4 py-2.5">
                                @if ($project->trashed())
                                    <span class="font-medium text-ink-muted">{{ $project->title }}</span>
                                @else
                                    <a href="{{ route('admin.projects.edit', $project) }}" wire:navigate class="font-medium hover:underline">{{ $project->title }}</a>
                                @endif
                                <span class="block font-mono text-xs text-ink-muted">{{ $project->slug }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-ink-muted">{{ $project->category?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <x-badge :tone="$project->status === \App\Enums\ProjectStatus::Published ? 'success' : 'neutral'">{{ $project->status->label() }}</x-badge>
                            </td>
                            <td class="px-4 py-2.5">
                                @unless ($project->trashed())
                                    <button type="button" wire:click="toggleFeatured({{ $project->id }})" aria-pressed="{{ $project->is_featured ? 'true' : 'false' }}"
                                        class="{{ $project->is_featured ? 'text-accent' : 'text-ink-muted' }} hover:underline">
                                        {{ $project->is_featured ? __('Featured') : __('Not featured') }}
                                    </button>
                                @endunless
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                @unless ($project->trashed())
                                    <button type="button" wire:click="move({{ $project->id }}, 'up')" class="rounded px-1.5 text-ink-muted hover:text-ink" aria-label="{{ __('Move :title up', ['title' => $project->title]) }}">↑</button>
                                    <button type="button" wire:click="move({{ $project->id }}, 'down')" class="rounded px-1.5 text-ink-muted hover:text-ink" aria-label="{{ __('Move :title down', ['title' => $project->title]) }}">↓</button>
                                @endunless
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                @if ($project->trashed())
                                    <button type="button" wire:click="restore({{ $project->id }})" class="text-accent hover:underline">{{ __('Restore') }}</button>
                                @else
                                    <a href="{{ route('admin.projects.media', $project) }}" wire:navigate class="me-3 text-accent hover:underline">{{ __('Media') }}</a>
                                    <button type="button" wire:click="togglePublished({{ $project->id }})" class="text-accent hover:underline">
                                        {{ $project->status === \App\Enums\ProjectStatus::Published ? __('Unpublish') : __('Publish') }}
                                    </button>
                                    <button type="button" wire:click="delete({{ $project->id }})" wire:confirm="{{ __('Move this project to trash?') }}" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{ $this->projects->links() }}
</div>
