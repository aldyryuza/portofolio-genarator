<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Log in')] class extends Component
{
    private const MAX_ATTEMPTS = 5;

    #[Validate('required|string|email|max:255')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        // Only the owner account may sign in; other users get the same generic error.
        $credentials = ['email' => $this->email, 'password' => $this->password, 'is_owner' => true];

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($this->throttleKey());
            $this->reset('password');

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $this->redirectIntended(route('admin.dashboard', absolute: false));
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
};
?>

<div>
    <h1 class="text-lg font-semibold">{{ __('Owner log in') }}</h1>

    @if (session('status'))
        <p class="mt-3 rounded-md bg-paper px-3 py-2 text-sm text-success" role="status">{{ session('status') }}</p>
    @endif

    <form wire:submit="login" class="mt-6 space-y-5" novalidate>
        <div>
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input wire:model="email" id="email" type="email" class="form-control" autocomplete="username" required autofocus
                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <p id="email-error" class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-baseline justify-between">
                <label for="password" class="form-label">{{ __('Password') }}</label>
                <a href="{{ route('password.request') }}" class="text-xs text-accent hover:underline">{{ __('Forgot password?') }}</a>
            </div>
            <input wire:model="password" id="password" type="password" class="form-control" autocomplete="current-password" required
                @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            @error('password') <p id="password-error" class="form-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-ink-muted">
            <input wire:model="remember" type="checkbox" class="size-4 rounded border-line-strong accent-accent">
            {{ __('Remember me') }}
        </label>

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">{{ __('Log in') }}</button>
    </form>
</div>
