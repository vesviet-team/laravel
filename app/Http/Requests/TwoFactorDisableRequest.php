<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorDisableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'current_password:customer'],
            'code' => ['required', 'string', 'size:6', "regex:/^\d{6}$/"],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'password.current_password' => 'Mật khẩu không chính xác.',
            'code.required' => 'Vui lòng nhập mã xác thực 6 chữ số.',
            'code.size' => 'Mã xác thực phải có 6 chữ số.',
            'code.regex' => 'Mã xác thực chỉ được chứa chữ số.',
        ];
    }

    public function attributes(): array
    {
        return [
            'password' => 'mật khẩu',
            'code' => 'mã xác thực',
        ];
    }
}
