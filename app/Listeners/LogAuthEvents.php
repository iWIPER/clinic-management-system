<?php

namespace App\Listeners;

use App\Models\AccessLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthEvents
{
    public function handleLogin(Login $event): void
    {
        try {
            $ua     = request()?->userAgent() ?? '';
            $parsed = AccessLog::parseUserAgent($ua);

            // Tenta obter a clínica da sessão ou da primeira clínica do usuário
            $clinicId = session('current_clinic_id')
                ?? $event->user->clinics()->value('clinics.id');

            AccessLog::create([
                'clinic_id'   => $clinicId,
                'user_id'     => $event->user->id,
                'action'      => AccessLog::ACTION_LOGIN,
                'description' => 'Login realizado',
                'ip_address'  => request()?->ip(),
                'user_agent'  => $ua,
                'device_type' => $parsed['device'],
                'browser'     => $parsed['browser'],
                'os'          => $parsed['os'],
                'created_at'  => now(),
            ]);

            // Atualiza last_login_at do usuário
            $event->user->updateQuietly(['last_login_at' => now()]);
        } catch (\Throwable) {
            // Nunca bloquear o login por falha no log
        }
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user) return;

        try {
            $ua     = request()?->userAgent() ?? '';
            $parsed = AccessLog::parseUserAgent($ua);

            AccessLog::create([
                'clinic_id'   => session('current_clinic_id'),
                'user_id'     => $event->user->id,
                'action'      => AccessLog::ACTION_LOGOUT,
                'description' => 'Logout',
                'ip_address'  => request()?->ip(),
                'user_agent'  => $ua,
                'device_type' => $parsed['device'],
                'browser'     => $parsed['browser'],
                'os'          => $parsed['os'],
                'created_at'  => now(),
            ]);
        } catch (\Throwable) {
            // Nunca bloquear o logout por falha no log
        }
    }
}
