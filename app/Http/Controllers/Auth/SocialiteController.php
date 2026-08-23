<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\RedirectsAfterAuthentication;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
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

        $email = trim((string) $socialiteUser->getEmail());

        if ($email === '') {
            Log::warning("Login social ({$driver}) sem e-mail retornado pelo provedor");
            event(new Failed('web', null, ['email' => null]));

            return redirect()->route('login')->withErrors([
                'email' => 'Não foi possível concluir o login: não recebemos um e-mail válido do provedor.',
            ]);
        }

        $user = $this->findOrCreateUser($socialiteUser, $providerColumn, $email);

        if (! $user) {
            event(new Failed('web', User::where('email', $email)->first(), ['email' => $email]));

            return redirect()->route('login')->withErrors([
                'email' => 'Já existe uma conta com este e-mail que ainda não foi verificada. Entre com e-mail e senha e verifique sua conta antes de usar este login.',
            ]);
        }

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return $this->redirectAfterAuthentication($user);
    }

    /**
     * Retorna null (nunca autentica, nunca cria/altera nada) quando o e-mail
     * do provedor já pertence a uma conta local ainda não verificada — vincular
     * automaticamente nesse caso permitiria account takeover: quem criou essa
     * conta local sabe a senha dela e continuaria com acesso depois do vínculo.
     * Contas já verificadas (dono comprovado do e-mail) podem ser vinculadas
     * normalmente.
     */
    private function findOrCreateUser(SocialiteUser $socialiteUser, string $providerColumn, string $email): ?User
    {
        $user = User::where($providerColumn, $socialiteUser->getId())->first();

        if ($user) {
            return $user;
        }

        $existingByEmail = User::where('email', $email)->first();

        if ($existingByEmail) {
            if (! $existingByEmail->email_verified_at) {
                return null;
            }

            $existingByEmail->forceFill([$providerColumn => $socialiteUser->getId()])->save();

            return $existingByEmail;
        }

        $user = User::create([
            'name' => $socialiteUser->getName() ?: $email,
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
            $providerColumn => $socialiteUser->getId(),
        ]);

        event(new Registered($user));

        return $user;
    }
}
