@props(['key' => 'status'])

@if (session($key))
    <p {{ $attributes->class('rounded-md border border-line bg-surface px-3 py-2 text-sm text-success') }} role="status">{{ session($key) }}</p>
@endif
