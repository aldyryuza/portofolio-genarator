@props(['eyebrow' => null, 'id' => null, 'level' => 'h2'])

<div {{ $attributes->class('max-w-2xl') }}>
    @if ($eyebrow)
        <p class="eyebrow">{{ $eyebrow }}</p>
    @endif
    <{{ $level }} @if ($id) id="{{ $id }}" @endif class="mt-2 font-display text-3xl leading-tight tracking-tight sm:text-4xl">{{ $slot }}</{{ $level }}>
</div>
