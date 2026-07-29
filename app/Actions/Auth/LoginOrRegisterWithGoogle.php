<?php

namespace App\Actions\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use RuntimeException;

/**
 * Links or creates an owner account from a Google login. See
 * docs/DECISIONS.md ADR-009: linking only ever happens by *verified* email,
 * and the social identity lives in its own table rather than being merged
 * into the users row directly.
 */
class LoginOrRegisterWithGoogle
{
    public function handle(SocialiteUser $socialiteUser): User
    {
        $existing = SocialAccount::query()
            ->where('provider', 'google')
            ->where('provider_user_id', $socialiteUser->getId())
            ->first();

        if ($existing !== null) {
            return $existing->user;
        }

        if (! $this->emailIsVerified($socialiteUser)) {
            throw new RuntimeException('Email Google Anda belum diverifikasi. Verifikasi email Anda di Google terlebih dahulu.');
        }

        $email = $socialiteUser->getEmail();

        if ($email === null) {
            throw new RuntimeException('Google tidak membagikan alamat email Anda.');
        }

        return DB::transaction(function () use ($socialiteUser, $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $socialiteUser->getName() ?: $socialiteUser->getNickname() ?: $email,
                    'email' => $email,
                    // Unusable random password: this account only ever
                    // authenticates via Google, never via the password form.
                    'password' => Hash::make(Str::random(40)),
                ]);

                // email_verified_at isn't in User's fillable list (mass
                // assignment shouldn't let arbitrary input mark an email
                // verified) — force it here specifically because Google
                // already vouched for this address.
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            SocialAccount::query()->create([
                'user_id' => $user->id,
                'provider' => 'google',
                'provider_user_id' => $socialiteUser->getId(),
                'provider_email' => $email,
            ]);

            return $user;
        });
    }

    private function emailIsVerified(SocialiteUser $socialiteUser): bool
    {
        // Every concrete Socialite provider (Two\User, One\User) extends
        // AbstractUser and so has getRaw(); the interface itself doesn't
        // declare it, hence the runtime check rather than a static type.
        if (! $socialiteUser instanceof AbstractUser) {
            return false;
        }

        $raw = $socialiteUser->getRaw();

        return (bool) ($raw['email_verified'] ?? $raw['verified_email'] ?? false);
    }
}
