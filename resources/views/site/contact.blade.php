@php($profile = app(\App\Support\SiteOwner::class)->profile())

<x-site.layout :title="__('Contact')" :canonical="route('contact')">
    <div class="container-site grid gap-12 py-16 sm:py-24 lg:grid-cols-[1fr_24rem]">
        <div class="max-w-xl">
            <x-site.section-heading :eyebrow="__('Contact')" level="h1">{{ __('Let’s talk') }}</x-site.section-heading>
            <p class="mt-4 text-lg text-ink-muted">{{ __('Tell me a little about your project or role. I usually reply within a few working days.') }}</p>

            @if (session('status'))
                <p class="mt-8 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success" role="status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('contact.store') }}" class="mt-8 space-y-5" novalidate>
                @csrf
                <input type="hidden" name="form_token" value="{{ $formToken }}">

                {{-- Honeypot: hidden from people and assistive technology, tempting for bots. --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">{{ __('Leave this field empty') }}</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="name" class="form-label">{{ __('Name') }}</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="120" required autocomplete="name" class="form-control"
                            @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                        @error('name') <p id="name-error" class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email" class="form-label">{{ __('Email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email" class="form-control"
                            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        @error('email') <p id="email-error" class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="subject" class="form-label">{{ __('Subject') }} <span class="font-normal text-ink-muted">({{ __('optional') }})</span></label>
                    <input id="subject" name="subject" type="text" value="{{ old('subject') }}" maxlength="160" class="form-control"
                        @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror>
                    @error('subject') <p id="subject-error" class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="message" class="form-label">{{ __('Message') }}</label>
                    <textarea id="message" name="message" rows="7" minlength="10" maxlength="5000" required class="form-control"
                        @error('message') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('message') }}</textarea>
                    @error('message') <p id="message-error" class="form-error">{{ $message }}</p> @enderror
                </div>

                <p class="text-xs text-ink-muted">{{ __('Your name, email and message are stored only so I can reply to you.') }}</p>

                <button type="submit" class="btn-primary px-5 py-2.5">{{ __('Send message') }}</button>
            </form>
        </div>

        <aside class="space-y-6 text-sm lg:pt-24" aria-label="{{ __('Other ways to reach me') }}">
            @if ($profile?->public_email)
                <div>
                    <p class="eyebrow">{{ __('Email') }}</p>
                    <a href="mailto:{{ $profile->public_email }}" class="mt-1 inline-block text-accent hover:underline">{{ $profile->public_email }}</a>
                </div>
            @endif
            @if ($profile?->location_public)
                <div>
                    <p class="eyebrow">{{ __('Based in') }}</p>
                    <p class="mt-1">{{ $profile->location_public }}</p>
                </div>
            @endif
        </aside>
    </div>
</x-site.layout>
