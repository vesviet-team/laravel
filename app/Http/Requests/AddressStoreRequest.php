<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddressStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:shipping,billing'],
            'label' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', "regex:/^(\+84|0)[0-9]{9,10}$/"],
            'address_line_1' => ['required', 'string', 'max:500'],
            'address_line_2' => ['nullable', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'ward' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_default' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Vui lòng chọn loại địa chỉ.',
            'type.in' => 'Loại địa chỉ không hợp lệ.',
            'recipient_name.required' => 'Vui lòng nhập tên người nhận.',
            'recipient_name.max' => 'Tên người nhận không được vượt quá 255 ký tự.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không đúng định dạng Việt Nam.',
            'address_line_1.required' => 'Vui lòng nhập địa chỉ chi tiết.',
            'address_line_1.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'city.required' => 'Vui lòng chọn thành phố.',
            'district.required' => 'Vui lòng chọn quận/huyện.',
            'ward.required' => 'Vui lòng chọn phường/xã.',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'loại địa chỉ',
            'label' => 'nhãn',
            'recipient_name' => 'tên người nhận',
            'phone' => 'số điện thoại',
            'address_line_1' => 'địa chỉ dòng 1',
            'address_line_2' => 'địa chỉ dòng 2',
            'city' => 'thành phố',
            'district' => 'quận/huyện',
            'ward' => 'phường/xã',
            'postal_code' => 'mã bưu chính',
            'country' => 'quốc gia',
            'is_default' => 'mặc định',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->phone ? preg_replace("/\s+/", '', $this->phone) : null,
            'recipient_name' => trim($this->recipient_name),
            'address_line_1' => trim($this->address_line_1),
            'address_line_2' => trim($this->address_line_2 ?? ''),
            'city' => trim($this->city),
            'district' => trim($this->district),
            'ward' => trim($this->ward ?? ''),
        ]);
    }
}
