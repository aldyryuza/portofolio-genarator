<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Account')] class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $account_current_password = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = $this->user()->name;
        $this->email = $this->user()->email;
    }

    public function updateAccount(): void
    {
        $user = $this->user();
        $emailChanged = $this->email !== $user->email;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            // Changing the login email requires re-entering the current password.
            'account_current_password' => [Rule::requiredIf($emailChanged), 'nullable', 'string', 'current_password'],
        ]);

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);
        $changed = array_keys($user->getDirty());
        $user->save();

        if ($changed !== []) {
            ActivityLog::record($user, 'account.updated', $user, ['changed' => $changed]);
        }

        $this->reset('account_current_password');
        session()->flash('account-status', __('Account saved.'));
    }

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user = $this->user();
        $user->update(['password' => $validated['password']]);
        ActivityLog::record($user, 'account.password_changed', $user);

        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('password-status', __('Password updated.'));
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
};
?>

<div class="max-w-2xl space-y-8">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Account') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Login details for the owner account. These are never shown publicly.') }}</p>
    </div>

    <form wire:submit="updateAccount" class="card space-y-5" novalidate>
        <h2 class="text-base font-semibold">{{ __('Login details') }}</h2>

        @if (session('account-status'))
            <p class="text-sm text-success" role="status">{{ session('account-status') }}</p>
        @endif

        <div>
            <label for="name" class="form-label">{{ __('Name') }}</label>
            <input wire:model="name" id="name" type="text" class="form-control" autocomplete="name" required
                @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name') <p id="name-error" class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="form-label">{{ __('Login email') }}</label>
            <input wire:model="email" id="email" type="email" class="form-control" autocomplete="username" required
                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <p id="email-error" class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="account_current_password" class="form-label">{{ __('Current password') }}</label>
            <input wire:model="account_current_password" id="account_current_password" type="password" class="form-control" autocomplete="current-password" aria-describedby="account_current_password-hint"
                @error('account_current_password') aria-invalid="true" @enderror>
            <p id="account_current_password-hint" class="form-hint">{{ __('Required only when changing the login email.') }}</p>
            @error('account_current_password') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="updateAccount">{{ __('Save') }}</button>
        </div>
    </form>

    <form wire:submit="updatePassword" class="card space-y-5" novalidate>
        <h2 class="text-base font-semibold">{{ __('Change password') }}</h2>

        @if (session('password-status'))
            <p class="text-sm text-success" role="status">{{ session('password-status') }}</p>
        @endif

        <div>
            <label for="current_password" class="form-label">{{ __('Current password') }}</label>
            <input wire:model="current_password" id="current_password" type="password" class="form-control" autocomplete="current-password" required
                @error('current_password') aria-invalid="true" aria-describedby="current_password-error" @enderror>
            @error('current_password') <p id="current_password-error" class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="form-label">{{ __('New password') }}</label>
            <input wire:model="password" id="password" type="password" class="form-control" autocomplete="new-password" required
                @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            @error('password') <p id="password-error" class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="form-label">{{ __('Confirm new password') }}</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password" class="form-control" autocomplete="new-password" required>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="updatePassword">{{ __('Update password') }}</button>
        </div>
    </form>
</div>
