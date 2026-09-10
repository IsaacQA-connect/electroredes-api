<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->email,
            $request->password
        );

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load('role')
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}
