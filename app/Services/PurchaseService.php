<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    /**
     * Create a new class instance.
     */
    //public function __construct(){//}
    public function __construct(
        private InventoryService $inventoryService
    ) {
    }

    public function getAll()
    {
        return Purchase::query()
            ->with(['supplier', 'user'])
            ->latest('purchase_date')
            ->paginate(20);
    }

    public function getById(Purchase $purchase): Purchase
    {
        return $purchase->load([
            'supplier',
            'user',
            'details.product',
        ]);
    }

    public function register(
        array $data,
        User $user
    ): Purchase {

        return DB::transaction(function () use ($data, $user) {

            $subtotal = 0;

            foreach ($data['details'] as $detail) {
                $subtotal +=
                    $detail['quantity'] *
                    $detail['unit_cost'];
            }

            $subtotal = round($subtotal, 2);

            $tax = round(
                (float) ($data['tax'] ?? 0),
                2
            );

            $total = round(
                $subtotal + $tax,
                2
            );

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'user_id' => $user->id,
                'purchase_date' => $data['purchase_date'] ?? now(),
                'document_type' => $data['document_type'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => PurchaseStatus::RECEIVED,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['details'] as $detail) {

                $lineSubtotal = round(
                    $detail['quantity'] *
                    $detail['unit_cost'],
                    2
                );

                $purchase->details()->create([
                    'product_id' => $detail['product_id'],
                    'quantity' => $detail['quantity'],
                    'unit_cost' => $detail['unit_cost'],
                    'subtotal' => $lineSubtotal,
                ]);
            }

            $this->inventoryService
                ->registerPurchaseEntry($purchase, $user);

            return $purchase->load([
                'supplier',
                'user',
                'details.product',
            ]);
        });
    }
}
