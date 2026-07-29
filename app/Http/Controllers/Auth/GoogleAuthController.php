<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginOrRegisterWithGoogle;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(LoginOrRegisterWithGoogle $loginOrRegisterWithGoogle): RedirectResponse
    {
        try {
            $socialiteUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors([
                'email' => 'Login dengan Google gagal. Silakan coba lagi.',
            ]);
        }

        try {
            $user = $loginOrRegisterWithGoogle->handle($socialiteUser);
        } catch (RuntimeException $exception) {
            return redirect()->route('login')->withErrors([
                'email' => $exception->getMessage(),
            ]);
        }

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
