<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService
    ) {
    }

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $customers = $this->customerService->getAll(
            $request->query('search')
        );

        return CustomerResource::collection($customers);
    }

    public function show(
        Customer $customer
    ): CustomerResource {
        return new CustomerResource($customer);
    }

    public function store(
        StoreCustomerRequest $request
    ): JsonResponse {
        $customer = $this->customerService->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Cliente creado correctamente.',
            'data' => new CustomerResource($customer),
        ], 201);
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer
    ): JsonResponse {
        $customer = $this->customerService->update(
            $customer,
            $request->validated()
        );

        return response()->json([
            'message' => 'Cliente actualizado correctamente.',
            'data' => new CustomerResource($customer),
        ]);
    }

    public function changeStatus(
        Customer $customer
    ): JsonResponse {
        $customer = $this->customerService->changeStatus(
            $customer,
            ! $customer->status
        );

        return response()->json([
            'message' => 'Estado del cliente actualizado.',
            'data' => new CustomerResource($customer),
        ]);
    }
}
