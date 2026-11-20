@php
    // Filtered views are not separate pages for search engines.
    $canonical = $projects->currentPage() > 1 && ! $isFiltered
        ? route('projects.index', ['page' => $projects->currentPage()])
        : route('projects.index');
@endphp

<x-site.layout :title="$activeCategory?->name ?? __('Projects')" :description="$activeCategory?->description" :canonical="$canonical" :noindex="$isFiltered">
    <div class="container-site py-16 sm:py-24">
        <x-site.section-heading :eyebrow="__('Work')" level="h1">
            {{ $activeCategory?->name ?? __('Projects') }}
        </x-site.section-heading>

        {{-- Plain GET form: filters work without JavaScript and are shareable links. --}}
        <form method="GET" action="{{ route('projects.index') }}" class="mt-10 grid gap-3 sm:grid-cols-[1fr_12rem_12rem_auto] sm:items-end" role="search">
            <div>
                <label for="q" class="form-label">{{ __('Search') }}</label>
                <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" class="form-control" placeholder="{{ __('Title or summary') }}">
            </div>
            <div>
                <label for="category" class="form-label">{{ __('Category') }}</label>
                <select id="category" name="category" class="form-control">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected($activeCategory?->is($category))>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="skill" class="form-label">{{ __('Skill') }}</label>
                <select id="skill" name="skill" class="form-control">
                    <option value="">{{ __('All skills') }}</option>
                    @foreach ($skills as $skill)
                        <option value="{{ $skill->slug }}" @selected($activeSkill?->is($skill))>{{ $skill->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary">{{ __('Filter') }}</button>
                @if ($isFiltered)
                    <a href="{{ route('projects.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                @endif
            </div>
        </form>

        <p class="mt-6 text-sm text-ink-muted" role="status">
            {{ trans_choice(':count project|:count projects', $projects->total()) }}
            @if ($activeSkill) · {{ __('using :skill', ['skill' => $activeSkill->name]) }} @endif
        </p>

        @if ($projects->isEmpty())
            <p class="mt-10 rounded-xl border border-dashed border-line p-10 text-center text-ink-muted">
                {{ $isFiltered ? __('No projects match these filters.') : __('Projects will appear here soon.') }}
            </p>
        @else
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($projects as $project)
                    <x-site.project-card :project="$project" heading-level="h2" />
                @endforeach
            </div>

            <div class="mt-12">{{ $projects->links() }}</div>
        @endif
    </div>
</x-site.layout>
