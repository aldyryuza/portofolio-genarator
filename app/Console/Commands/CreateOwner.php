<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateOwner extends Command
{
    protected $signature = 'portfolio:create-owner
                            {--name= : Display name of the owner}
                            {--email= : Login email of the owner}';

    protected $description = 'Create the single site owner account (password is prompted, never defaulted)';

    public function handle(): int
    {
        if (User::where('is_owner', true)->exists()) {
            $this->error('An owner account already exists. Use password reset instead of creating another owner.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->option('name') ?: $this->ask('Name'),
            'email' => $this->option('email') ?: $this->ask('Email'),
            'password' => $this->secret('Password'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $owner = DB::transaction(function () use ($validator): User {
            $validated = $validator->validated();

            $owner = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $owner->forceFill(['is_owner' => true, 'email_verified_at' => now()])->save();

            ActivityLog::record(null, 'owner.created', $owner);

            return $owner;
        });

        $this->info("Owner account created for {$owner->email}.");

        return self::SUCCESS;
    }
}
