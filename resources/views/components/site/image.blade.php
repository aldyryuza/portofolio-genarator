@props(['media', 'variant' => 'large', 'sizes' => '100vw', 'alt' => null, 'eager' => false])

{{-- Only pass public assets here; the URL itself also enforces visibility. --}}
@php
    $size = $media->variantSize($variant);
    $srcset = collect(\App\Models\MediaAsset::IMAGE_VARIANTS)
        ->map(fn (string $name) => ($dimensions = $media->variantSize($name)) ? $media->url($name).' '.$dimensions[0].'w' : null)
        ->filter()
        ->unique()
        ->implode(', ');
@endphp

<img src="{{ $media->url($variant) }}"
    @if ($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
    @if ($size) width="{{ $size[0] }}" height="{{ $size[1] }}" @endif
    alt="{{ $alt ?? $media->alt_text ?? '' }}"
    loading="{{ $eager ? 'eager' : 'lazy' }}"
    decoding="async"
    {{ $attributes }}>
