<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('site.contact', ['formToken' => ContactRequest::freshToken()]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $success = redirect()->route('contact')->with('status', __('Thank you — your message has been sent.'));

        // Bots get the same success response, so they learn nothing about the traps.
        if ($request->isLikelySpam()) {
            Log::info('Contact form submission dropped as spam.', ['ip_hash' => ContactMessage::hashIp($request->ip())]);

            return $success;
        }

        $validated = $request->validated();

        $message = ContactMessage::create([
            'sender_name' => $validated['name'],
            'sender_email' => $validated['email'],
            'subject' => filled($validated['subject'] ?? null) ? $validated['subject'] : null,
            'body' => $validated['message'],
        ]);
        $message->forceFill(['ip_hash' => ContactMessage::hashIp($request->ip())])->save();

        $this->notifyOwner($message);

        return $success;
    }

    /**
     * The message is already stored; a mail failure must not lose it or show an error to the visitor.
     */
    private function notifyOwner(ContactMessage $message): void
    {
        try {
            User::where('is_owner', true)->first()?->notify(new ContactMessageReceived($message));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
