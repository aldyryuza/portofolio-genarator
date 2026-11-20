<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;

class ContactRequest extends FormRequest
{
    /** Humans need at least this long to fill in the form. */
    public const MIN_SECONDS = 3;

    /** Old form tokens are refused so a single page load cannot be replayed forever. */
    public const MAX_SECONDS = 2 * 60 * 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            // Spam traps; checked in isLikelySpam() rather than failing validation visibly.
            'website' => ['nullable', 'string', 'max:255'],
            'form_token' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Bots fill the hidden honeypot or submit faster than a person can type.
     */
    public function isLikelySpam(): bool
    {
        if (filled($this->input('website'))) {
            return true;
        }

        try {
            $renderedAt = (int) Crypt::decryptString((string) $this->input('form_token'));
        } catch (DecryptException) {
            return true;
        }

        $elapsed = now()->getTimestamp() - $renderedAt;

        return $elapsed < self::MIN_SECONDS || $elapsed > self::MAX_SECONDS;
    }

    public static function freshToken(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }
}
