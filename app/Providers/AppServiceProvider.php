<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ==========================================
        // 1. CORREO DE VERIFICACIÓN DE CUENTA
        // ==========================================
        
        // Formatear URL dirigida hacia Vue.js
        VerifyEmail::createUrlUsing(function (object $notifiable) {
            $verifyUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(60),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            return config('app.frontend_url') . '/verify-email?url=' . urlencode($verifyUrl);
        });

        // Personalizar mensaje y texto en ESPAÑOL
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verifica tu correo electrónico - Electro Redes')
                ->greeting('¡Hola, ' . ($notifiable->name ?? 'Usuario') . '!')
                ->line('Gracias por registrarte en Electro Redes. Para activar tu cuenta y acceder a todos nuestras funciones, por favor confirma tu correo electrónico.')
                ->action('Verificar mi correo electrónico', $url)
                ->line('Este enlace de verificación caducará en 60 minutos.')
                ->line('Si no creaste una cuenta en Electro Redes, puedes ignorar este mensaje.')
                ->salutation('Atentamente, el equipo de Electro Redes.');
        });


        // ==========================================
        // 2. CORREO DE RESTABLECIMIENTO DE CONTRASEÑA
        // ==========================================
        
        // Formatear URL dirigida hacia Vue.js
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . "/reset-password?token={$token}&email={$notifiable->getEmailForPasswordReset()}";
        });

        // Personalizar mensaje y texto en ESPAÑOL
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = config('app.frontend_url') . "/reset-password?token={$token}&email={$notifiable->getEmailForPasswordReset()}";

            return (new MailMessage)
                ->subject('Restablecer tu contraseña - Electro Redes')
                ->greeting('¡Hola!')
                ->line('Recibiste este correo porque solicitaste un restablecimiento de contraseña para tu cuenta en Electro Redes.')
                ->action('Restablecer Contraseña', $url)
                ->line('Este enlace para restablecer la contraseña caducará en 60 minutos.')
                ->line('Si no solicitaste un restablecimiento de contraseña, no se requiere ninguna otra acción.')
                ->salutation('Atentamente, el equipo de Electro Redes.');
        });
    }
}