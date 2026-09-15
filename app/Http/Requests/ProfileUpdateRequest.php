<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:customers,email,'.$this->user('customer')?->id],
            'phone' => ['nullable', 'string', 'max:20', 'unique:customers,phone,'.$this->user('customer')?->id, "regex:/^(\+84|0)[0-9]{9,10}$/"],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'avatar' => ['nullable', File::types(['jpeg', 'png', 'webp'])->max(2 * 1024)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ và tên.',
            'name.max' => 'Họ và tên không được vượt quá 255 ký tự.',
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'email.unique' => 'Email này đã được đăng ký.',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'phone.unique' => 'Số điện thoại này đã được đăng ký.',
            'phone.regex' => 'Số điện thoại không đúng định dạng Việt Nam.',
            'date_of_birth.date' => 'Ngày sinh không hợp lệ.',
            'date_of_birth.before' => 'Ngày sinh phải là ngày trong quá khứ.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'avatar.max' => 'Ảnh đại diện không được vượt quá 2MB.',
            'avatar.types' => 'Ảnh đại diện phải có định dạng JPEG, PNG hoặc WebP.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'họ và tên',
            'email' => 'email',
            'phone' => 'số điện thoại',
            'date_of_birth' => 'ngày sinh',
            'gender' => 'giới tính',
            'avatar' => 'ảnh đại diện',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->email)),
            'phone' => $this->phone ? preg_replace("/\s+/", '', $this->phone) : null,
            'name' => trim($this->name),
        ]);
    }
}
