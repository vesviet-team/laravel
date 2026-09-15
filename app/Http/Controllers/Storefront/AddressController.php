<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddressStoreRequest;
use App\Http\Requests\AddressUpdateRequest;
use App\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    public function index()
    {
        $customer = Auth::guard('customer')->user();
        $addresses = $customer->addresses()->orderBy('is_default', 'desc')->orderBy('created_at', 'desc')->get();

        return view('storefront.account.addresses', compact('addresses', 'customer'));
    }

    public function create()
    {
        $customer = Auth::guard('customer')->user();

        return view('storefront.account.address-create', compact('customer'));
    }

    public function store(AddressStoreRequest $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        $validated = $request->validated();
        $validated['customer_id'] = $customer->id;

        $existingCount = $customer->addresses()->where('type', $validated['type'])->count();
        if ($existingCount === 0) {
            $validated['is_default'] = true;
        }

        CustomerAddress::create($validated);

        return redirect()->route('account.addresses')->with('success', 'Thêm địa chỉ thành công.');
    }

    public function edit(CustomerAddress $address)
    {
        $this->authorizeAddress($address);
        $customer = Auth::guard('customer')->user();

        return view('storefront.account.address-edit', compact('address', 'customer'));
    }

    public function update(AddressUpdateRequest $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($address);
        $validated = $request->validated();
        $address->update($validated);

        return redirect()->route('account.addresses')->with('success', 'Cập nhật địa chỉ thành công.');
    }

    public function destroy(CustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($address);
        $wasDefault = $address->is_default;
        $type = $address->type;
        $customer = Auth::guard('customer')->user();

        $address->delete();

        if ($wasDefault) {
            $nextAddress = $customer->addresses()->where('type', $type)->first();
            if ($nextAddress) {
                $nextAddress->update(['is_default' => true]);
            }
        }

        return redirect()->route('account.addresses')->with('success', 'Xóa địa chỉ thành công.');
    }

    public function setDefault(CustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($address);
        $address->update(['is_default' => true]);

        return redirect()->route('account.addresses')->with('success', 'Đã đặt làm địa chỉ mặc định.');
    }

    private function authorizeAddress(CustomerAddress $address): void
    {
        $customer = Auth::guard('customer')->user();
        if ($address->customer_id !== $customer->id) {
            abort(403, 'Không có quyền truy cập địa chỉ này.');
        }
    }
}
