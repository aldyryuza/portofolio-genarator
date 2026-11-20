@props(['name', 'label', 'live' => false])

<div>
    <label class="flex items-center gap-2 text-sm text-ink">
        <input type="checkbox" @if ($live) wire:model.live="{{ $name }}" @else wire:model="{{ $name }}" @endif {{ $attributes->class('size-4 rounded border-line-strong accent-accent') }}>
        {{ $label }}
    </label>
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
