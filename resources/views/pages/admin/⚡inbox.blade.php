<?php

use App\Enums\ContactMessageStatus;
use App\Models\ActivityLog;
use App\Models\ContactMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Inbox')] class extends Component
{
    use WithPagination;

    #[Url(except: 'inbox')]
    public string $filter = 'inbox';

    #[Locked]
    public ?int $openId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ContactMessage::class);
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
        $this->openId = null;
    }

    /**
     * @return LengthAwarePaginator<int, ContactMessage>
     */
    #[Computed]
    public function messages(): LengthAwarePaginator
    {
        return ContactMessage::query()
            ->when($this->filter === 'archived', fn ($query) => $query->where('status', ContactMessageStatus::Archived))
            ->when($this->filter !== 'archived', fn ($query) => $query->where('status', '!=', ContactMessageStatus::Archived))
            ->latest('id')
            ->paginate(25);
    }

    #[Computed]
    public function openMessage(): ?ContactMessage
    {
        return $this->openId ? ContactMessage::find($this->openId) : null;
    }

    public function open(int $id): void
    {
        $message = ContactMessage::findOrFail($id);
        $this->authorize('update', $message);

        $message->markAsRead();
        $this->openId = $message->id;
    }

    public function close(): void
    {
        $this->openId = null;
    }

    public function toggleArchived(int $id): void
    {
        $message = ContactMessage::findOrFail($id);
        $this->authorize('update', $message);

        $archived = $message->status === ContactMessageStatus::Archived;
        $message->forceFill(['status' => $archived ? ContactMessageStatus::Read : ContactMessageStatus::Archived])->save();
        ActivityLog::record(Auth::user(), $archived ? 'contact_message.unarchived' : 'contact_message.archived', $message);

        $this->openId = null;
    }

    public function delete(int $id): void
    {
        $message = ContactMessage::findOrFail($id);
        $this->authorize('delete', $message);

        $message->delete();
        ActivityLog::record(Auth::user(), 'contact_message.deleted', $message);

        $this->openId = null;
        session()->flash('status', __('Message deleted.'));
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Inbox') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ __('Messages sent through the contact form.') }}</p>
        </div>
        <div class="sm:w-44">
            <label for="filter" class="form-label">{{ __('Show') }}</label>
            <select wire:model.live="filter" id="filter" class="form-control">
                <option value="inbox">{{ __('Inbox') }}</option>
                <option value="archived">{{ __('Archived') }}</option>
            </select>
        </div>
    </div>

    <x-flash />

    <div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <div class="overflow-hidden rounded-lg border border-line bg-surface">
            @if ($this->messages->isEmpty())
                <p class="p-6 text-sm text-ink-muted">{{ __('No messages.') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($this->messages as $message)
                        <li wire:key="message-{{ $message->id }}">
                            <button type="button" wire:click="open({{ $message->id }})"
                                @class(['block w-full px-4 py-3 text-left hover:bg-paper focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent', 'bg-paper' => $openId === $message->id])
                                @if ($openId === $message->id) aria-current="true" @endif>
                                <span class="flex items-center justify-between gap-3">
                                    <span @class(['truncate', 'font-semibold' => $message->status === \App\Enums\ContactMessageStatus::New])>{{ $message->sender_name }}</span>
                                    <time class="shrink-0 text-xs text-ink-muted" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->diffForHumans() }}</time>
                                </span>
                                <span class="mt-0.5 block truncate text-sm text-ink-muted">
                                    @if ($message->status === \App\Enums\ContactMessageStatus::New)<span class="sr-only">{{ __('Unread:') }}</span>@endif
                                    {{ $message->subject ?? \Illuminate\Support\Str::limit($message->body, 80) }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <section class="card h-fit" aria-live="polite" aria-label="{{ __('Message') }}">
            @if ($current = $this->openMessage)
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $current->subject ?? __('(no subject)') }}</h2>
                        <p class="mt-1 text-sm text-ink-muted">
                            {{ $current->sender_name }} &lt;<a href="mailto:{{ $current->sender_email }}" class="text-accent hover:underline">{{ $current->sender_email }}</a>&gt;
                            · {{ $current->created_at->translatedFormat('j M Y, H:i') }}
                        </p>
                    </div>
                    <button type="button" wire:click="close" class="btn-secondary px-3 py-1.5 text-xs">{{ __('Close') }}</button>
                </div>
                {{-- Visitor input: escaped by x-site.prose, never rendered as HTML. --}}
                <x-site.prose :text="$current->body" class="mt-5 text-sm" />
                <div class="mt-6 flex flex-wrap gap-2 border-t border-line pt-4">
                    <a href="mailto:{{ $current->sender_email }}?subject={{ rawurlencode('Re: '.($current->subject ?? '')) }}" class="btn-primary">{{ __('Reply by email') }}</a>
                    <button type="button" wire:click="toggleArchived({{ $current->id }})" class="btn-secondary">
                        {{ $current->status === \App\Enums\ContactMessageStatus::Archived ? __('Move to inbox') : __('Archive') }}
                    </button>
                    <button type="button" wire:click="delete({{ $current->id }})" wire:confirm="{{ __('Delete this message permanently?') }}" class="btn-secondary text-danger">{{ __('Delete') }}</button>
                </div>
            @else
                <p class="text-sm text-ink-muted">{{ __('Select a message to read it.') }}</p>
            @endif
        </section>
    </div>

    {{ $this->messages->links() }}
</div>
