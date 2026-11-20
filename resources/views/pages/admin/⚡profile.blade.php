<?php

use App\Enums\AvailabilityStatus;
use App\Models\ActivityLog;
use App\Models\MediaAsset;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Public profile')] class extends Component
{
    public string $display_name = '';

    public ?string $headline = null;

    public ?string $summary = null;

    public ?string $biography = null;

    public ?string $location_public = null;

    public ?string $public_email = null;

    public string $availability_status = '';

    public ?int $avatar_media_id = null;

    public function mount(): void
    {
        $profile = $this->profile();
        $this->authorize('update', $profile);

        $this->fill($profile->only([
            'display_name', 'headline', 'summary', 'biography', 'location_public', 'public_email', 'avatar_media_id',
        ]));
        $this->availability_status = ($profile->availability_status ?? AvailabilityStatus::NotAvailable)->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:120'],
            'headline' => ['nullable', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:500'],
            'biography' => ['nullable', 'string', 'max:5000'],
            'location_public' => ['nullable', 'string', 'max:120'],
            'public_email' => ['nullable', 'string', 'email', 'max:255'],
            'availability_status' => ['required', Rule::enum(AvailabilityStatus::class)],
            // The avatar is shown publicly, so only public, non-deleted images qualify.
            'avatar_media_id' => ['nullable', 'integer', Rule::exists(MediaAsset::class, 'id')
                ->whereIn('mime_type', MediaAsset::IMAGE_MIME_TYPES)
                ->where('visibility', 'public')
                ->whereNull('deleted_at')],
        ];
    }

    public function save(): void
    {
        $profile = $this->profile();
        $this->authorize('update', $profile);

        $validated = array_map(
            fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value,
            $this->validate(),
        );

        DB::transaction(function () use ($profile, $validated): void {
            $profile->fill($validated);
            $changed = array_keys($profile->getDirty());
            $profile->save();

            if ($changed !== []) {
                ActivityLog::record($this->user(), 'profile.updated', $profile, ['changed' => $changed]);
            }
        });

        session()->flash('status', __('Profile saved.'));
    }

    /**
     * @return Collection<int, MediaAsset>
     */
    #[Computed]
    public function avatarOptions(): Collection
    {
        return MediaAsset::query()->images()->where('visibility', 'public')->latest('id')->get();
    }

    private function profile(): Profile
    {
        $user = $this->user();

        return $user->profile ?? $user->profile()->make(['display_name' => $user->name]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
};
?>

<div class="max-w-3xl">
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('Public profile') }}</h1>
    <p class="mt-1 text-sm text-ink-muted">{{ __('This information is shown on the public portfolio. Only write facts you can stand behind.') }}</p>

    @if (session('status'))
        <p class="mt-4 rounded-md border border-line bg-surface px-3 py-2 text-sm text-success" role="status">{{ session('status') }}</p>
    @endif

    <form wire:submit="save" class="card mt-6 space-y-5" novalidate>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="display_name" class="form-label">{{ __('Display name') }}</label>
                <input wire:model="display_name" id="display_name" type="text" class="form-control" maxlength="120" required
                    @error('display_name') aria-invalid="true" aria-describedby="display_name-error" @enderror>
                @error('display_name') <p id="display_name-error" class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="headline" class="form-label">{{ __('Headline') }}</label>
                <input wire:model="headline" id="headline" type="text" class="form-control" maxlength="160"
                    @error('headline') aria-invalid="true" aria-describedby="headline-error" @enderror>
                @error('headline') <p id="headline-error" class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="summary" class="form-label">{{ __('Short summary') }}</label>
            <textarea wire:model="summary" id="summary" rows="3" class="form-control" maxlength="500" aria-describedby="summary-hint"
                @error('summary') aria-invalid="true" @enderror></textarea>
            <p id="summary-hint" class="form-hint">{{ __('Up to 500 characters. Used in the home page hero.') }}</p>
            @error('summary') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="biography" class="form-label">{{ __('Biography') }}</label>
            <textarea wire:model="biography" id="biography" rows="8" class="form-control" maxlength="5000" aria-describedby="biography-hint"
                @error('biography') aria-invalid="true" @enderror></textarea>
            <p id="biography-hint" class="form-hint">{{ __('Plain text, up to 5000 characters. HTML is not rendered.') }}</p>
            @error('biography') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="location_public" class="form-label">{{ __('Public location') }}</label>
                <input wire:model="location_public" id="location_public" type="text" class="form-control" maxlength="120" placeholder="{{ __('e.g. Jakarta, Indonesia') }}"
                    @error('location_public') aria-invalid="true" aria-describedby="location_public-error" @enderror>
                @error('location_public') <p id="location_public-error" class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="public_email" class="form-label">{{ __('Public email') }}</label>
                <input wire:model="public_email" id="public_email" type="email" class="form-control" aria-describedby="public_email-hint"
                    @error('public_email') aria-invalid="true" @enderror>
                <p id="public_email-hint" class="form-hint">{{ __('Optional. Can differ from your login email.') }}</p>
                @error('public_email') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="availability_status" class="form-label">{{ __('Availability') }}</label>
            <select wire:model="availability_status" id="availability_status" class="form-control sm:max-w-xs"
                @error('availability_status') aria-invalid="true" aria-describedby="availability_status-error" @enderror>
                @foreach (\App\Enums\AvailabilityStatus::cases() as $status)
                    <option value="{{ $status->value }}" wire:key="status-{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
            @error('availability_status') <p id="availability_status-error" class="form-error">{{ $message }}</p> @enderror
        </div>

        <x-form.select name="avatar_media_id" :label="__('Avatar')" class="sm:max-w-xs" :hint="__('Choose from public images in the media library.')">
            <option value="">{{ __('No avatar') }}</option>
            @foreach ($this->avatarOptions as $image)
                <option value="{{ $image->id }}" wire:key="avatar-{{ $image->id }}">{{ $image->alt_text ?: $image->original_filename }}</option>
            @endforeach
        </x-form.select>

        <div class="flex justify-end border-t border-line pt-5">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Save profile') }}</button>
        </div>
    </form>
</div>
