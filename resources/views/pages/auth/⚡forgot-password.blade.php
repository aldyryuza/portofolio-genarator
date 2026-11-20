<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Forgot password')] class extends Component
{
    private const MAX_ATTEMPTS = 5;

    #[Validate('required|string|email|max:255')]
    public string $email = '';

    public function sendResetLink(): void
    {
        $this->validate();

        $key = 'password-reset:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        RateLimiter::hit($key, 15 * 60);

        // The broker status is ignored so the response never reveals whether the email exists.
        Password::sendResetLink(['email' => $this->email, 'is_owner' => true]);

        $this->reset('email');
        session()->flash('status', __('If that email belongs to the owner account, a reset link has been sent.'));
    }
};
?>

<div>
    <h1 class="text-lg font-semibold">{{ __('Forgot password') }}</h1>
    <p class="mt-2 text-sm text-ink-muted">{{ __('Enter the owner email address and we will send a password reset link.') }}</p>

    @if (session('status'))
        <p class="mt-3 rounded-md bg-paper px-3 py-2 text-sm text-success" role="status">{{ session('status') }}</p>
    @endif

    <form wire:submit="sendResetLink" class="mt-6 space-y-5" novalidate>
        <div>
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input wire:model="email" id="email" type="email" class="form-control" autocomplete="username" required autofocus
                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <p id="email-error" class="form-error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">{{ __('Send reset link') }}</button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" class="text-accent hover:underline">{{ __('Back to log in') }}</a>
    </p>
</div>
