<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6', "regex:/^\d{6}$/"],
            'recovery_code' => ['nullable', 'string', 'size:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Vui lòng nhập mã xác thực 6 chữ số.',
            'code.size' => 'Mã xác thực phải có 6 chữ số.',
            'code.regex' => 'Mã xác thực chỉ được chứa chữ số.',
            'recovery_code.size' => 'Mã khôi phục phải có 8 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'mã xác thực',
            'recovery_code' => 'mã khôi phục',
        ];
    }
}
