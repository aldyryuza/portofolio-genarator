<?php

use App\Enums\SocialPlatform;
use App\Models\ActivityLog;
use App\Models\Profile;
use App\Models\SocialLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Social links')] class extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $platform = 'linkedin';

    public ?string $label = null;

    public string $url = '';

    public ?int $sort_order = 0;

    public bool $is_visible = true;

    public function mount(): void
    {
        $this->authorize('viewAny', SocialLink::class);
    }

    #[Computed]
    public function profile(): ?Profile
    {
        return $this->user()->profile;
    }

    /**
     * @return Collection<int, SocialLink>
     */
    #[Computed]
    public function links(): Collection
    {
        return $this->profile?->socialLinks()->ordered()->get() ?? new Collection;
    }

    public function edit(int $id): void
    {
        $link = $this->ownedLink($id);
        $this->authorize('update', $link);

        $this->resetValidation();
        $this->editingId = $link->id;
        $this->fill($link->only(['label', 'url', 'sort_order', 'is_visible']));
        $this->platform = $link->platform->value;
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->reset();
    }

    public function save(): void
    {
        $profile = $this->profile;

        if ($profile === null) {
            $this->addError('url', __('Save your public profile first.'));

            return;
        }

        $link = $this->editingId ? $this->ownedLink($this->editingId) : $profile->socialLinks()->make();
        $this->authorize($link->exists ? 'update' : 'create', $link->exists ? $link : SocialLink::class);

        $validated = $this->validate([
            'platform' => ['required', Rule::enum(SocialPlatform::class)],
            'label' => ['nullable', 'string', 'max:60'],
            // Only web links; blocks javascript:, data: and similar schemes.
            'url' => ['required', 'string', 'max:500', 'url:https,http'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_visible' => ['boolean'],
        ]);

        $link->fill([...$validated, 'label' => filled($validated['label']) ? $validated['label'] : null]);
        $isNew = ! $link->exists;
        $link->save();

        ActivityLog::record(Auth::user(), $isNew ? 'social_link.created' : 'social_link.updated', $link);

        $this->cancel();
        session()->flash('status', __('Link saved.'));
    }

    public function delete(int $id): void
    {
        $link = $this->ownedLink($id);
        $this->authorize('delete', $link);

        $link->delete();
        ActivityLog::record(Auth::user(), 'social_link.deleted', $link);

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('status', __('Link deleted.'));
    }

    private function ownedLink(int $id): SocialLink
    {
        return SocialLink::where('profile_id', $this->profile?->id)->findOrFail($id);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Social links') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Shown in the site footer and on the About page.') }}</p>
    </div>

    <x-flash />

    @if ($this->profile === null)
        <div class="card text-sm text-ink-muted">
            {{ __('Save your public profile first.') }}
            <a href="{{ route('admin.profile') }}" wire:navigate class="text-accent hover:underline">{{ __('Edit profile') }}</a>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
            <form wire:submit="save" class="card h-fit space-y-4" novalidate>
                <h2 class="text-base font-semibold">{{ $editingId ? __('Edit link') : __('New link') }}</h2>

                <x-form.select name="platform" :label="__('Platform')">
                    @foreach (\App\Enums\SocialPlatform::cases() as $option)
                        <option value="{{ $option->value }}" wire:key="platform-{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="url" type="url" :label="__('URL')" maxlength="500" placeholder="https://" required />
                <x-form.input name="label" :label="__('Label')" maxlength="60" :hint="__('Optional. Defaults to the platform name.')" />
                <div class="grid grid-cols-2 items-end gap-3">
                    <x-form.input name="sort_order" type="number" :label="__('Sort order')" min="0" />
                    <x-form.checkbox name="is_visible" :label="__('Visible')" />
                </div>

                <div class="flex justify-end gap-2 border-t border-line pt-4">
                    @if ($editingId)
                        <button type="button" wire:click="cancel" class="btn-secondary">{{ __('Cancel') }}</button>
                    @endif
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Save') }}</button>
                </div>
            </form>

            <div class="overflow-x-auto rounded-lg border border-line bg-surface">
                @if ($this->links->isEmpty())
                    <p class="p-6 text-sm text-ink-muted">{{ __('No links yet.') }}</p>
                @else
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-line text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Link') }}</th>
                                <th scope="col" class="px-4 py-2.5 font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($this->links as $link)
                                <tr wire:key="link-{{ $link->id }}">
                                    <td class="px-4 py-2.5">
                                        <span class="font-medium">{{ $link->displayLabel() }}</span>
                                        @unless ($link->is_visible)
                                            <x-badge class="ms-1">{{ __('Hidden') }}</x-badge>
                                        @endunless
                                        <span class="block truncate text-xs text-ink-muted">{{ $link->url }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                        <button type="button" wire:click="edit({{ $link->id }})" class="text-accent hover:underline">{{ __('Edit') }}</button>
                                        <button type="button" wire:click="delete({{ $link->id }})" wire:confirm="{{ __('Delete this link?') }}" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endif
</div>
