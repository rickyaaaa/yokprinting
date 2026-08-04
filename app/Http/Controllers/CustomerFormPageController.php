<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Contracts\View\View;

class CustomerFormPageController extends Controller
{
    public function create(): View
    {
        return view('customers.form', [
            'customerId' => null,
            'customer' => $this->emptyCustomer(),
            'isEdit' => false,
            'title' => 'Tambah pelanggan baru',
        ]);
    }

    public function edit(string $customer): View
    {
        $model = Customer::query()
            ->where(fn ($query) => $query->where('code', $customer)->orWhere('id', $customer))
            ->firstOrFail();

        return view('customers.form', [
            'customerId' => $model->getKey(),
            'customer' => [
                'code' => $model->code,
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone,
                'taxNumber' => $model->tax_number,
                'address' => $model->address,
                'city' => $model->city,
                'province' => $model->province,
                'postalCode' => $model->postal_code,
                'status' => $model->status === Customer::STATUS_ACTIVE ? 'Aktif' : 'Nonaktif',
                'notes' => $model->notes,
            ],
            'isEdit' => true,
            'title' => 'Edit pelanggan '.$model->code,
        ]);
    }

    /** @return array<string, string|null> */
    private function emptyCustomer(): array
    {
        return [
            'code' => '',
            'name' => '',
            'email' => '',
            'phone' => '',
            'taxNumber' => '',
            'address' => '',
            'city' => '',
            'province' => '',
            'postalCode' => '',
            'status' => 'Aktif',
            'notes' => '',
        ];
    }
}
