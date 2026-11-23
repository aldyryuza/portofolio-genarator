<?php

use App\Enums\ProjectStatus;
use App\Enums\ContactMessageStatus;
use App\Models\ActivityLog;
use App\Models\ContactMessage;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Services\Analytics\AnalyticsReport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    #[Computed]
    public function profile(): ?Profile
    {
        return Auth::user()?->profile;
    }

    /**
     * Share of the public profile fields that are filled in (0-100).
     */
    #[Computed]
    public function profileCompleteness(): int
    {
        $fields = ['display_name', 'headline', 'summary', 'biography', 'location_public', 'public_email'];
        $filled = collect($fields)->filter(fn (string $field): bool => filled($this->profile?->{$field}))->count();

        return (int) round($filled / count($fields) * 100);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return [
            'published' => Project::query()->published()->count(),
            'drafts' => Project::query()->where('status', ProjectStatus::Draft)->count(),
            'featured' => Project::query()->published()->where('is_featured', true)->count(),
            'skills' => Skill::query()->count(),
            'unread' => ContactMessage::query()->where('status', ContactMessageStatus::New)->count(),
            'visitors' => (new AnalyticsReport(30))->totals()['visitors'],
        ];
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    #[Computed]
    public function recentActivity(): Collection
    {
        return ActivityLog::query()->latest('id')->limit(10)->get();
    }
};
?>

<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Dashboard') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Welcome back, :name.', ['name' => auth()->user()->name]) }}</p>
    </div>

    @if ($this->counts['unread'] > 0)
        <a href="{{ route('admin.inbox') }}" wire:navigate class="card flex items-center justify-between gap-3 border-accent/40 hover:bg-paper">
            <span class="text-sm">{{ trans_choice(':count new message|:count new messages', $this->counts['unread']) }}</span>
            <span class="text-sm font-medium text-accent">{{ __('Open inbox') }} →</span>
        </a>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <section class="card" aria-labelledby="profile-heading">
            <h2 id="profile-heading" class="text-sm font-medium text-ink-muted">{{ __('Public profile') }}</h2>
            <p class="mt-2 text-3xl font-semibold">{{ $this->profileCompleteness }}%</p>
            <p class="mt-1 text-sm text-ink-muted">{{ __('of profile fields completed') }}</p>
            <div class="mt-3 h-1.5 rounded-full bg-paper" role="presentation">
                <div class="h-1.5 rounded-full bg-accent" style="width: {{ $this->profileCompleteness }}%"></div>
            </div>
            <a href="{{ route('admin.profile') }}" wire:navigate class="mt-4 inline-block text-sm text-accent hover:underline">{{ __('Edit profile') }}</a>
        </section>

        <section class="card sm:col-span-1 lg:col-span-2" aria-labelledby="content-heading">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="content-heading" class="text-sm font-medium text-ink-muted">{{ __('Content') }}</h2>
                <a href="{{ route('admin.projects.create') }}" wire:navigate class="text-sm text-accent hover:underline">{{ __('New project') }}</a>
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-5">
                @foreach ([
                    'published' => __('Published projects'),
                    'drafts' => __('Drafts'),
                    'featured' => __('Featured (live)'),
                    'skills' => __('Skills'),
                    'visitors' => __('Visitors (30 days)'),
                ] as $key => $label)
                    <div wire:key="count-{{ $key }}">
                        <dt class="text-xs text-ink-muted">{{ $label }}</dt>
                        <dd class="mt-1 text-2xl font-semibold">{{ $this->counts[$key] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    <section aria-labelledby="activity-heading">
        <h2 id="activity-heading" class="text-lg font-semibold">{{ __('Recent activity') }}</h2>

        @if ($this->recentActivity->isEmpty())
            <p class="mt-3 text-sm text-ink-muted">{{ __('No activity recorded yet.') }}</p>
        @else
            <div class="mt-3 overflow-x-auto rounded-lg border border-line bg-surface">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Event') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Details') }}</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('When') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->recentActivity as $log)
                            <tr wire:key="activity-{{ $log->id }}">
                                <td class="px-4 py-2.5 font-mono text-xs">{{ $log->event }}</td>
                                <td class="px-4 py-2.5 text-ink-muted">{{ implode(', ', $log->properties_json['changed'] ?? []) ?: '—' }}</td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-ink-muted">
                                    <time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->diffForHumans() }}</time>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
