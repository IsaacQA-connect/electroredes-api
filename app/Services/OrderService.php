<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {
    }

    public function create(array $data, User $user): Order
    {
        return DB::transaction(function () use ($data, $user) {

            $subtotal = 0;

            $details = [];

            foreach ($data['details'] as $detail) {

                $product = Product::query()
                    ->lockForUpdate()
                    ->findOrFail($detail['product_id']);

                if (!$product->status) {
                    throw ValidationException::withMessages([
                        'product' => [
                            "El producto {$product->name} está inactivo."
                        ],
                    ]);
                }

                if ($product->stock < $detail['quantity']) {
                    throw ValidationException::withMessages([
                        'stock' => [
                            "Stock insuficiente para {$product->name}. " .
                            "Disponible: {$product->stock}"
                        ],
                    ]);
                }

                $unitPrice = $product->sale_price;

                $lineSubtotal = $unitPrice * $detail['quantity'];

                $subtotal += $lineSubtotal;

                $details[] = [
                    'product_id' => $product->id,
                    'quantity' => $detail['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $discount = $data['discount'] ?? 0;

            if ($discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount' => [
                        'El descuento no puede ser mayor al subtotal.'
                    ],
                ]);
            }

            $total = $subtotal - $discount;

            $order = Order::create([
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $user->id,
                'channel' => $data['channel'],
                'status' => OrderStatus::PENDING,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'order_date' => $data['order_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($details as $detail) {
                $order->details()->create($detail);
            }

            $this->registerPayments(
                $order,
                $data['payments'] ?? []
            );

            if ($order->channel === 'POS') {

                $this->inventoryService->registerSaleExit(
                    $order->load('details'),
                    $user
                );

                $order->update([
                    'status' => OrderStatus::COMPLETED,
                ]);
            }

            $order->load('details.product', 'payments');

            return $order;
        });
    }

    private function registerPayments(
        Order $order,
        array $payments
    ): void {
        
        if ($order->channel === 'POS' && empty($payments)) {
            throw ValidationException::withMessages([
                'payments' => [
                    'Una venta POS debe registrar al menos un pago.'
                ],
            ]);
        }

        $totalPaid = 0;

        foreach ($payments as $payment) {

            $method = PaymentMethod::from($payment['method']);

            $status = match ($order->channel) {
                'POS' => PaymentStatus::APPROVED,
                'WEB' => $method === PaymentMethod::ONLINE_PAYMENT
                    ? PaymentStatus::PENDING
                    : PaymentStatus::APPROVED,
                default => PaymentStatus::PENDING,
            };

            $order->payments()->create([
                'method' => $method,
                'amount' => $payment['amount'],
                'status' => $status,
                'transaction_code' => $payment['transaction_code'] ?? null,
                'payment_date' => now(),
            ]);

            if ($status === PaymentStatus::APPROVED) {
                $totalPaid += $payment['amount'];
            }
        }

        if ($order->channel === 'POS') {

            if (round($totalPaid, 2) !== round((float) $order->total, 2)) {
                throw ValidationException::withMessages([
                    'payments' => [
                        "El total pagado (S/ {$totalPaid}) " .
                        "no coincide con el total de la venta (S/ {$order->total})."
                    ],
                ]);
            }
        }
    }
}