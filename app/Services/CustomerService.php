<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    /**
     * Create a new class instance.
     */
    //public function __construct(){    //}
    public function getAll(
        ?string $search = null
    ): LengthAwarePaginator {
        $query = Customer::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere(
                        'document_number',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'phone',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        return $query
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    public function getById(int $id): Customer
    {
        return Customer::findOrFail($id);
    }

    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    public function update(
        Customer $customer,
        array $data
    ): Customer {
        $customer->update($data);

        return $customer->refresh();
    }

    public function changeStatus(
        Customer $customer,
        bool $status
    ): Customer {
        $customer->update([
            'status' => $status,
        ]);

        return $customer->refresh();
    }
}
