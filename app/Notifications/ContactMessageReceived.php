<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ContactMessageReceived extends Notification
{
    public function __construct(public ContactMessage $contactMessage) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Message text is passed as plain lines, which the mail template escapes.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->contactMessage;

        return (new MailMessage)
            ->subject(__('New message: :subject', ['subject' => Str::limit($message->subject ?? __('(no subject)'), 80)]))
            ->replyTo($message->sender_email, $message->sender_name)
            ->line(__('From: :name <:email>', ['name' => $message->sender_name, 'email' => $message->sender_email]))
            ->line(Str::limit($message->body, 2000))
            ->action(__('Open inbox'), route('admin.inbox'));
    }
}
