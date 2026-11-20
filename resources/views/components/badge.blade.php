@props(['tone' => 'neutral'])

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
    'bg-paper text-ink-muted ring-1 ring-line' => $tone === 'neutral',
    'bg-success/10 text-success' => $tone === 'success',
    'bg-accent/10 text-accent' => $tone === 'accent',
]) }}>{{ $slot }}</span>
