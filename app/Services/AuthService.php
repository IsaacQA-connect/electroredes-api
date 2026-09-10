<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Create a new class instance.
     */
    //public function __construct(){//}
    public function login(string $email, string $password): array
    {
        $user = User::with('role')
            ->where('email', $email)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        if (!$user->status) {
            throw ValidationException::withMessages([
                'email' => ['El usuario se encuentra inactivo.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
