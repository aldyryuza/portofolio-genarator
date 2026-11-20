<?php

namespace App\Models;

use App\Enums\ContactMessageStatus;
use Carbon\CarbonInterface;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $sender_name
 * @property string $sender_email
 * @property string|null $subject
 * @property string $body
 * @property ContactMessageStatus $status
 * @property CarbonInterface|null $read_at
 * @property string|null $ip_hash
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['sender_name', 'sender_email', 'subject', 'body'])]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
    ];

    public function markAsRead(): void
    {
        if ($this->status === ContactMessageStatus::New) {
            $this->forceFill(['status' => ContactMessageStatus::Read, 'read_at' => now()])->save();
        }
    }

    /**
     * Keyed hash so repeated senders can be spotted without keeping their IP address.
     */
    public static function hashIp(?string $ip): ?string
    {
        return $ip === null ? null : hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactMessageStatus::class,
            'read_at' => 'datetime',
        ];
    }
}
