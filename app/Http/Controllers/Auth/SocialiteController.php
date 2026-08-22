<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\RedirectsAfterAuthentication;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialiteController extends Controller
{
    use RedirectsAfterAuthentication;

    public function redirectToGoogle(): RedirectResponse
    {
        $this->abortIfNotConfigured('google_login');

        return Socialite::driver('google_login')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        $this->abortIfNotConfigured('google_login');

        return $this->completeLogin('google_login', 'google_id');
    }

    public function redirectToApple(): RedirectResponse
    {
        $this->abortIfNotConfigured('apple_login');

        return Socialite::driver('apple_login')->redirect();
    }

    public function handleAppleCallback(): RedirectResponse
    {
        $this->abortIfNotConfigured('apple_login');

        return $this->completeLogin('apple_login', 'apple_id');
    }

    private function abortIfNotConfigured(string $service): void
    {
        abort_unless(
            (bool) config("services.{$service}.client_id"),
            Response::HTTP_NOT_FOUND
        );
    }

    private function completeLogin(string $driver, string $providerColumn): RedirectResponse
    {
        try {
            $socialiteUser = Socialite::driver($driver)->user();
        } catch (\Throwable $e) {
            Log::warning("Falha no callback de login social ({$driver})", ['message' => $e->getMessage()]);

            return redirect()->route('login')->withErrors([
                'email' => 'Não foi possível concluir o login. Tente novamente.',
            ]);
        }

        $user = $this->findOrCreateUser($socialiteUser, $providerColumn);

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return $this->redirectAfterAuthentication($user);
    }

    private function findOrCreateUser(SocialiteUser $socialiteUser, string $providerColumn): User
    {
        $user = User::where($providerColumn, $socialiteUser->getId())->first();

        if ($user) {
            return $user;
        }

        // E-mail já cadastrado (conta criada com senha) tentando entrar pelo
        // provedor social pela primeira vez: vincula em vez de duplicar.
        $user = User::where('email', $socialiteUser->getEmail())->first();

        if ($user) {
            $user->forceFill([$providerColumn => $socialiteUser->getId()])->save();

            return $user;
        }

        $user = User::create([
            'name' => $socialiteUser->getName() ?: $socialiteUser->getEmail(),
            'email' => $socialiteUser->getEmail(),
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
            $providerColumn => $socialiteUser->getId(),
        ]);

        event(new Registered($user));

        return $user;
    }
}
