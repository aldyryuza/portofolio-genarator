@props(['name', 'label', 'hint' => null, 'live' => false])

@php
    $id = $attributes->get('id', str_replace('.', '_', $name));
    $describedBy = trim(($hint ? "{$id}-hint " : '').($errors->has($name) ? "{$id}-error" : ''));
@endphp

<div>
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id }}" @if ($live) wire:model.live="{{ $name }}" @else wire:model="{{ $name }}" @endif
        {{ $attributes->except('id')->class('form-control') }}
        @if ($errors->has($name)) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
        {{ $slot }}
    </select>
    @if ($hint)
        <p id="{{ $id }}-hint" class="form-hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $id }}-error" class="form-error">{{ $message }}</p>
    @enderror
</div>
