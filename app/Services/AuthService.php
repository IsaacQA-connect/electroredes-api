<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function login(array $credentials): array
    {
        $user = User::with('role', 'customer')->where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $customerRole = Role::where('name', 'CLIENTE')->firstOrFail();

            $user = User::create([
                'role_id' => $customerRole->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $customer = Customer::create([
                'user_id' => $user->id,
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'status' => true,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user' => $user->load('role', 'customer'),
                'token' => $token,
            ];
        });
    }

    public function logout(User $user): void
    {
        $user::currentAccessToken()->delete();
    }
}