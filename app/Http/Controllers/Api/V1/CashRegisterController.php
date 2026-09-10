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

    public function current(Request $request): JsonResponse
    {
        $register = CashRegister::with(['movements.user', 'user'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'OPEN')
            ->first();

        return response()->json(['data' => $register]);
    }

    public function open(OpenCashRegisterRequest $request): JsonResponse
    {
        $register = $this->cashRegisterService->open($request->validated(), $request->user());

        return response()->json([
            'message' => 'Caja abierta correctamente.',
            'data' => $register,
        ], 201);
    }

    public function close(CloseCashRegisterRequest $request, CashRegister $cashRegister): JsonResponse
    {
        $register = $this->cashRegisterService->close($cashRegister, $request->validated());

        return response()->json([
            'message' => 'Caja cerrada correctamente.',
            'data' => $register,
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