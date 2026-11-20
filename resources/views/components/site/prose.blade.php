@props(['text' => null])

{{-- Admin content is plain text: escape first, then turn blank lines into paragraphs. Never render raw HTML. --}}
@if (filled($text))
    <div {{ $attributes->class('space-y-4 leading-relaxed text-ink/90') }}>
        @foreach (preg_split('/\R{2,}/', trim($text)) as $paragraph)
            <p>{!! nl2br(e(trim($paragraph))) !!}</p>
        @endforeach
    </div>
@endif
