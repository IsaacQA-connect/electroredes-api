<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashRegisterRequest;
use App\Http\Requests\OpenCashRegisterRequest;
use App\Http\Requests\StoreCashMovementRequest;
use App\Models\CashRegister;
use App\Services\CashRegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    public function __construct(
        protected CashRegisterService $cashRegisterService
    ) {}

    public function current(Request $request)
    {
        // Buscar solo cajas con estado OPEN del usuario (o generales según tu lógica)
        $cashRegister = CashRegister::with(['user', 'movements'])
            ->where('status', 'OPEN')
            ->latest()
            ->first();

        if (!$cashRegister) {
            return response()->json([
                'message' => 'No hay una caja abierta actualmente',
                'data' => null
            ], 200); // Retornar 200 con data null facilita el manejo en Vue
        }

        return response()->json([
            'data' => $cashRegister
        ]);
    }

    public function open(OpenCashRegisterRequest $request): JsonResponse
    {
        $register = $this->cashRegisterService->open($request->validated(), $request->user());

        return response()->json([
            'message' => 'Caja abierta correctamente.',
            'data' => $register,
        ], 201);
    }

    public function close(Request $request, $id)
    {
        // 1. Validar que llegue 'actual_balance'
        $request->validate([
            'actual_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $cashRegister = CashRegister::findOrFail($id);

        // 2. Verificar que no esté cerrada ya
        if ($cashRegister->status === 'CLOSED') {
            return response()->json(['message' => 'La caja ya se encuentra cerrada.'], 400);
        }

        // 3. Actualizar la caja (MANTENIENDO el user_id original)
        $cashRegister->update([
            'actual_balance' => $request->actual_balance,
            'notes' => $request->notes,
            'status' => 'CLOSED',
            'closed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Caja cerrada exitosamente.',
            'data' => $cashRegister
        ]);
    }

    public function addMovement(StoreCashMovementRequest $request, CashRegister $cashRegister): JsonResponse
    {
        $movement = $this->cashRegisterService->addMovement(
            $cashRegister,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Movimiento de caja registrado correctamente.',
            'data' => $movement,
        ], 201);
    }
}