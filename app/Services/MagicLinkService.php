<?php

namespace App\Services;

use App\Mail\MagicLinkMail;
use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MagicLinkService
{
    public function generate(string $email, ?string $firstName = null, ?string $lastName = null): MagicLink
    {
        // Invalidate existing magic links for this email
        MagicLink::query()
            ->where('email', $email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $magicLink = MagicLink::query()->create([
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes(15),
        ]);

        Mail::to($email)->send(new MagicLinkMail($magicLink));

        return $magicLink;
    }

    public function verify(string $token): ?User
    {
        $magicLink = MagicLink::query()
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $magicLink) {
            return null;
        }

        $magicLink->update(['used_at' => now()]);

        return User::query()->firstOrCreate(
            ['email' => $magicLink->email],
            [
                'first_name' => $magicLink->first_name ?? '',
                'last_name' => $magicLink->last_name ?? '',
            ],
        );
    }
}
