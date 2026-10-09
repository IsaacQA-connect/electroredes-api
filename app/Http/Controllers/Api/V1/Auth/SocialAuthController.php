<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirigir al proveedor OAuth (Google)
     */
    public function redirectToProvider(string $provider): RedirectResponse
    {
        if ($provider !== 'google') {
            return redirect(config('app.frontend_url') . '/auth/login?error=provider_not_supported');
        }

        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Manejar la respuesta del proveedor OAuth (Google)
     */
    public function handleProviderCallback(string $provider): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return redirect(config('app.frontend_url') . '/auth/login?error=oauth_failed');
        }

        $user = DB::transaction(function () use ($socialUser) {
            // 1. Buscar si el usuario ya existe por google_id o por email
            $existingUser = User::where('google_id', $socialUser->getId())
                ->orWhere('email', $socialUser->getEmail())
                ->first();

            if ($existingUser) {
                // Vincular google_id si aún no lo tenía y verificar correo
                $existingUser->update([
                    'google_id' => $socialUser->getId(),
                    'email_verified_at' => $existingUser->email_verified_at ?? now(),
                ]);

                return $existingUser;
            }

            // 2. Si no existe, crear un nuevo usuario con rol CLIENTE
            $customerRole = Role::where('name', 'CLIENTE')->firstOrFail();

            $newUser = User::create([
                'role_id'           => $customerRole->id,
                'name'              => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Usuario Google',
                'email'             => $socialUser->getEmail(),
                'google_id'         => $socialUser->getId(),
                'password'          => bcrypt(Str::random(24)), // Contraseña aleatoria
                'email_verified_at' => now(), // Los correos de Google ya vienen verificados
            ]);

            // 3. Crear el registro asociado de Customer
            Customer::create([
                'user_id'         => $newUser->id,
                'document_type'   => 'DNI',
                'document_number' => '00000000', // Se solicitará completar en su perfil
                'name'            => $newUser->name,
                'phone'           => null,
                'email'           => $newUser->email,
                'address'         => null,
                'status'          => true,
            ]);

            return $newUser;
        });

        // Generar Token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;
        $userRole = Str::upper($user->role->name ?? 'CLIENTE');

        // Redirigir al Frontend Vue enviando Token y Rol
        $frontendUrl = config('app.frontend_url') . '/auth/social-callback';
        return redirect()->away("{$frontendUrl}?token={$token}&role={$userRole}");
    }
}