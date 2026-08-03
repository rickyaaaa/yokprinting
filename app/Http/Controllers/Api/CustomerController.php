<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create($request->validated());

        return response()->json([
            'data' => $this->serializeCustomer($customer),
            'message' => 'Customer created successfully.',
            'redirect_url' => route('customers.index', ['created' => $customer->code]),
        ], 201);
    }

    /**
     * Display a customer profile.
     */
    public function show(Customer $customer): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeCustomer($customer),
        ]);
    }

    /**
     * Update a customer profile.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return response()->json([
            'data' => $this->serializeCustomer($customer->refresh()),
            'message' => 'Customer updated successfully.',
        ]);
    }

    /**
     * Soft delete a customer profile.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(status: 204);
    }

    /**
     * Transform a customer model for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->getKey(),
            'code' => $customer->code,
            'name' => $customer->name,
            'activity_status' => $customer->activity_status,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'city' => $customer->city,
            'province' => $customer->province,
            'postal_code' => $customer->postal_code,
            'tax_number' => $customer->tax_number,
            'status' => $customer->status,
            'last_order_at' => $customer->last_order_at?->toISOString(),
            'notes' => $customer->notes,
            'initials' => $customer->initials(),
            'created_at' => $customer->created_at?->toISOString(),
            'updated_at' => $customer->updated_at?->toISOString(),
        ];
    }
}
