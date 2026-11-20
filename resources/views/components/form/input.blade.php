@props(['name', 'label', 'type' => 'text', 'hint' => null])

@php
    $id = $attributes->get('id', str_replace('.', '_', $name));
    $describedBy = trim(($hint ? "{$id}-hint " : '').($errors->has($name) ? "{$id}-error" : ''));
@endphp

<div>
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <input id="{{ $id }}" type="{{ $type }}" wire:model="{{ $name }}"
        {{ $attributes->except('id')->class('form-control') }}
        @if ($errors->has($name)) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
    @if ($hint)
        <p id="{{ $id }}-hint" class="form-hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $id }}-error" class="form-error">{{ $message }}</p>
    @enderror
</div>
