<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashRegisterService
{
    public function open(array $data, User $user): CashRegister
    {
        $hasOpenRegister = CashRegister::query()
            ->where('user_id', $user->id)
            ->where('status', 'OPEN')
            ->exists();

        if ($hasOpenRegister) {
            throw ValidationException::withMessages([
                'cash_register' => ['Ya tienes una caja abierta. Debes cerrarla antes de abrir otra.']
            ]);
        }

        return CashRegister::create([
            'user_id' => $user->id,
            'opening_balance' => $data['opening_balance'] ?? 0,
            'status' => 'OPEN',
            'opened_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function close(CashRegister $cashRegister, array $data): CashRegister
    {
        if ($cashRegister->status !== 'OPEN') {
            throw ValidationException::withMessages([
                'cash_register' => ['Esta caja ya se encuentra cerrada.']
            ]);
        }

        return DB::transaction(function () use ($cashRegister, $data) {
            $inflows = $cashRegister->movements()->where('type', 'INFLOW')->sum('amount');
            $outflows = $cashRegister->movements()->where('type', 'OUTFLOW')->sum('amount');

            $expectedBalance = (float) $cashRegister->opening_balance + (float) $inflows - (float) $outflows;
            $actualBalance = (float) $data['actual_balance'];
            $difference = $actualBalance - $expectedBalance;

            $cashRegister->update([
                'closing_balance' => round($expectedBalance, 2),
                'actual_balance' => round($actualBalance, 2),
                'difference' => round($difference, 2),
                'status' => 'CLOSED',
                'closed_at' => now(),
                'notes' => $data['notes'] ?? $cashRegister->notes,
            ]);

            return $cashRegister;
        });
    }

    public function addMovement(CashRegister $cashRegister, array $data, User $user): CashMovement
    {
        if ($cashRegister->status !== 'OPEN') {
            throw ValidationException::withMessages([
                'cash_register' => ['No se pueden registrar movimientos en una caja cerrada.']
            ]);
        }

        return $cashRegister->movements()->create([
            'user_id' => $user->id,
            'type' => $data['type'], // INFLOW u OUTFLOW
            'amount' => $data['amount'],
            'description' => $data['description'],
            'reference_type' => $data['reference_type'] ?? 'manual',
            'reference_id' => $data['reference_id'] ?? null,
        ]);
    }
}