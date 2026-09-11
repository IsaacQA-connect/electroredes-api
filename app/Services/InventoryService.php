<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementDetail;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderReturn;

class InventoryService
{
    /**
     * Create a new class instance.
     */
    //public function __construct(){//}
    public function registerPurchaseEntry(
        Purchase $purchase,
        User $user
    ): InventoryMovement {

        return DB::transaction(function () use ($purchase, $user) {

            $alreadyRegistered = InventoryMovement::query()
                ->where('type', InventoryMovementType::PURCHASE_ENTRY)
                ->where('reference_type', 'purchase')
                ->where('reference_id', $purchase->id)
                ->exists();

            if ($alreadyRegistered) {
                throw ValidationException::withMessages([
                    'purchase' => [
                        'Esta compra ya tiene registrada una entrada de inventario.'
                    ],
                ]);
            }

            $movement = InventoryMovement::create([
                'user_id' => $user->id,
                'type' => InventoryMovementType::PURCHASE_ENTRY,
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
                'movement_date' => $purchase->purchase_date,
                'notes' => 'Ingreso de mercadería por compra.',
            ]);

            foreach ($purchase->details as $detail) {

                $product = Product::query()
                    ->lockForUpdate()
                    ->findOrFail($detail->product_id);

                $oldStock = (float) $product->stock;
                $oldCost = (float) $product->cost;

                $quantity = (float) $detail->quantity;
                $unitCost = (float) $detail->unit_cost;

                $newStock = $oldStock + $quantity;

                if ($newStock > 0) {
                    $newCost = (
                        ($oldStock * $oldCost) +
                        ($quantity * $unitCost)
                    ) / $newStock;
                } else {
                    $newCost = $unitCost;
                }

                $product->stock = round($newStock, 2);
                $product->cost = round($newCost, 2);
                $product->save();

                $movement->details()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ]);
            }

            return $movement->load('details.product');
        });
    }

    public function registerInitialStock(
        array $data,
        User $user
    ): InventoryMovement {

        return DB::transaction(function () use ($data, $user) {

            if (
                InventoryMovement::where(
                    'type',
                    InventoryMovementType::INITIAL_STOCK
                )->exists()
            ) {
                throw ValidationException::withMessages([
                    'details' => 'El inventario inicial ya fue registrado.',
                ]);
            }

            $movement = InventoryMovement::create([
                'user_id' => $user->id,
                'type' => InventoryMovementType::INITIAL_STOCK,
                'reference_type' => 'initial_stock',
                'reference_id' => null,
                'movement_date' => $data['movement_date'],
                'notes' => $data['notes']
                    ?? 'Registro de inventario inicial.',
            ]);

            foreach ($data['details'] as $detail) {

                $product = Product::query()
                    ->lockForUpdate()
                    ->findOrFail($detail['product_id']);

                $quantity = (float) $detail['quantity'];
                $unitCost = (float) $detail['unit_cost'];

                $product->stock = round($quantity, 2);
                $product->cost = round($unitCost, 2);
                $product->save();

                InventoryMovementDetail::create([
                    'inventory_movement_id' => $movement->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ]);
            }

            return $movement->load('details.product');
        });
    }

    public function getStock(array $filters = [])
    {
        return Product::query()
            ->with('category')
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $filters['category_id'] ?? null,
                fn ($query, $categoryId) =>
                    $query->where('category_id', $categoryId)
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    public function getLowStock()
    {
        return Product::query()
            ->with('category')
            ->whereColumn('stock', '<=', 'minimum_stock')
            ->where('status', true)
            ->orderBy('stock')
            ->paginate(20);
    }

    public function getMovements(array $filters = [])
    {
        return InventoryMovement::query()
            ->with([
                'user',
                'details.product',
            ])
            ->when(
                $filters['type'] ?? null,
                fn ($query, $type) =>
                    $query->where('type', $type)
            )
            ->when(
                $filters['product_id'] ?? null,
                function ($query, $productId) {
                    $query->whereHas(
                        'details',
                        fn ($query) =>
                            $query->where('product_id', $productId)
                    );
                }
            )
            ->latest('movement_date')
            ->paginate(20)
            ->withQueryString();
    }

    public function getInventoryValuation(): array
    {
        $products = Product::query()
            ->where('status', true)
            ->get([
                'id',
                'name',
                'stock',
                'cost',
            ]);

        $total = $products->sum(
            fn ($product) =>
                (float) $product->stock *
                (float) $product->cost
        );

        return [
            'total_value' => round($total, 2),
            'products_count' => $products->count(),
        ];
    }

    public function registerAdjustment(
        array $data,
        User $user,
        InventoryMovementType $type
    ): InventoryMovement {

        return DB::transaction(function () use ($data, $user, $type) {

            $movement = InventoryMovement::create([
                'user_id' => $user->id,
                'type' => $type,
                'reference_type' => 'inventory_adjustment',
                'reference_id' => null,
                'movement_date' => $data['movement_date'],
                'notes' => $data['notes'],
            ]);

            foreach ($data['details'] as $detail) {

                $product = Product::query()
                    ->lockForUpdate()
                    ->findOrFail($detail['product_id']);

                $quantity = (float) $detail['quantity'];
                $currentStock = (float) $product->stock;
                $currentCost = (float) $product->cost;

                if (
                    $type === InventoryMovementType::ADJUSTMENT_EXIT
                    && $quantity > $currentStock
                ) {
                    throw ValidationException::withMessages([
                        'details' =>
                            "El producto {$product->name} ".
                            "no tiene stock suficiente para realizar ".
                            "el ajuste."
                    ]);
                }

                if (
                    $type === InventoryMovementType::ADJUSTMENT_ENTRY
                ) {
                    $newStock = $currentStock + $quantity;
                } else {
                    $newStock = $currentStock - $quantity;
                }

                $product->stock = round($newStock, 2);
                $product->save();

                InventoryMovementDetail::create([
                    'inventory_movement_id' => $movement->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $currentCost,
                ]);
            }

            return $movement->load([
                'user',
                'details.product',
            ]);
        });
    }

    public function registerSaleExit(
        Order $order,
        User $user
    ): InventoryMovement {
        
        return DB::transaction(function () use ($order, $user) {

            $movement = InventoryMovement::create([
                'user_id' => $user->id,
                'type' => InventoryMovementType::SALE_EXIT,
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'movement_date' => now(),
                'notes' => 'Salida de inventario por venta',
            ]);

            foreach ($order->details as $detail) {

                $product = Product::query()
                    ->lockForUpdate()
                    ->findOrFail($detail->product_id);

                if ($product->stock < $detail->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => [
                            "Stock insuficiente para el producto: {$product->name}"
                        ],
                    ]);
                }

                $product->stock -= $detail->quantity;
                $product->save();

                $movement->details()->create([
                    'product_id' => $product->id,
                    'quantity' => $detail->quantity,
                    'unit_cost' => $product->cost,
                ]);
            }

            return $movement->load('details.product');
        });
    }

    public function registerSaleReturn(
        OrderReturn $orderReturn,
        User $user
    ): InventoryMovement {

        return DB::transaction(function () use ($orderReturn, $user) {

            $movement = InventoryMovement::create([
                'user_id' => $user->id,
                'type' => InventoryMovementType::RETURN_ENTRY,
                'reference_type' => 'order_return',
                'reference_id' => $orderReturn->id,
                'movement_date' => $orderReturn->return_date,
                'notes' => 'Ingreso de inventario por devolución de venta.',
            ]);

            foreach ($orderReturn->details as $returnDetail) {

                $orderDetail = $returnDetail->orderDetail;

                $product = Product::query()
                    ->lockForUpdate()
                    ->findOrFail($orderDetail->product_id);

                $quantity = (float) $returnDetail->quantity;

                $product->stock = round(
                    (float) $product->stock + $quantity,
                    2
                );

                $product->save();

                $movement->details()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $product->cost,
                ]);
            }

            return $movement->load('details.product');
        });
    }

    public function registerAdjustmentEntry(
        array $data,
        User $user
    ): InventoryMovement {

        return $this->registerAdjustment(
            $data,
            $user,
            InventoryMovementType::ADJUSTMENT_ENTRY
        );
    }

    public function registerAdjustmentExit(
        array $data,
        User $user
    ): InventoryMovement {

        return $this->registerAdjustment(
            $data,
            $user,
            InventoryMovementType::ADJUSTMENT_EXIT
        );
    }
}
