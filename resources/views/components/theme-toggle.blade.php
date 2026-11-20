{{-- Hidden until resources/js/theme.js runs, so visitors without JavaScript never see a dead control. --}}
<button type="button" data-theme-toggle hidden aria-pressed="false"
    {{ $attributes->class('inline-flex size-9 items-center justify-center rounded-md border border-line-strong bg-surface text-ink-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent') }}>
    <span class="sr-only">{{ __('Dark mode') }}</span>
    {{-- Moon in light mode, sun in dark mode. --}}
    <svg aria-hidden="true" class="size-4.5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" /></svg>
    <svg aria-hidden="true" class="hidden size-4.5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="4" /><path stroke-linecap="round" d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
</button>
