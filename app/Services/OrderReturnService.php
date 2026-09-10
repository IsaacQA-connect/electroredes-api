<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderReturnService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {
    }

    public function create(
        Order $order,
        array $data,
        User $user
    ): OrderReturn {

        return DB::transaction(function () use (
            $order,
            $data,
            $user
        ) {

            if ($order->status !== OrderStatus::COMPLETED) {
                throw ValidationException::withMessages([
                    'order' => [
                        'Solo se pueden devolver ventas completadas.'
                    ],
                ]);
            }

            $order->load([
                'details',
                'returns.details',
            ]);

            $returnDetails = [];
            $total = 0;

            foreach ($data['details'] as $detail) {

                $orderDetail = $order->details
                    ->firstWhere('id', $detail['order_detail_id']);

                if (!$orderDetail) {
                    throw ValidationException::withMessages([
                        'details' => [
                            "El detalle de venta {$detail['order_detail_id']} "
                            . "no pertenece a esta venta."
                        ],
                    ]);
                }

                $quantityRequested = (float) $detail['quantity'];

                $quantityReturned = $order->returns
                    ->flatMap->details
                    ->where(
                        'order_detail_id',
                        $orderDetail->id
                    )
                    ->sum('quantity');

                $availableQuantity =
                    (float) $orderDetail->quantity
                    - (float) $quantityReturned;

                if ($quantityRequested > $availableQuantity) {
                    throw ValidationException::withMessages([
                        'details' => [
                            "No puedes devolver {$quantityRequested} "
                            . "unidades de {$orderDetail->product_id}. "
                            . "Cantidad disponible para devolución: "
                            . "{$availableQuantity}."
                        ],
                    ]);
                }

                $unitPrice = (float) $orderDetail->unit_price;

                $subtotal = $quantityRequested * $unitPrice;

                $total += $subtotal;

                $returnDetails[] = [
                    'order_detail_id' => $orderDetail->id,
                    'quantity' => $quantityRequested,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            $orderReturn = OrderReturn::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'total' => round($total, 2),
                'reason' => $data['reason'] ?? null,
                'return_date' => $data['return_date'] ?? now(),
            ]);

            foreach ($returnDetails as $detail) {
                $orderReturn->details()->create($detail);
            }

            $this->inventoryService->registerSaleReturn(
                $orderReturn->load('details.orderDetail'),
                $user
            );

            return $orderReturn->load([
                'order',
                'user',
                'details.orderDetail.product',
            ]);
        });
    }
}