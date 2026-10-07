<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // Listar usuarios con búsqueda y paginación
    public function index(Request $request): JsonResponse
    {
        $query = User::with('role');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate(10);

        return response()->json($users);
    }

    // Listar todos los roles para el Selector en Vue
    public function roles(): JsonResponse
    {
        return response()->json(Role::all());
    }

    // Crear un nuevo usuario desde el Admin
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id'  => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => Hash::make($validated['password']),
            'role_id'           => $validated['role_id'],
            'email_verified_at' => now(), // Verificado automáticamente al ser creado por un admin
        ]);

        return response()->json([
            'message' => 'Usuario creado exitosamente.',
            'user'    => $user->load('role')
        ], 201);
    }

    // Actualizar usuario
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role_id'  => 'required|exists:roles,id',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role_id = $validated['role_id'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'user'    => $user->load('role')
        ]);
    }

    // Eliminar usuario
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.'
        ]);
    }

    // Cambiar estado de verificación (Verificado <-> Pendiente)
    public function toggleVerification(User $user): JsonResponse
    {
        if ($user->hasVerifiedEmail()) {
            $user->email_verified_at = null;
            $message = 'El usuario ahora está en estado Pendiente (sin verificar).';
        } else {
            $user->email_verified_at = now();
            $message = 'El usuario ha sido verificado manualmente.';
        }

        $user->save();

        return response()->json([
            'message' => $message,
            'user'    => $user->load('role')
        ]);
    }
}