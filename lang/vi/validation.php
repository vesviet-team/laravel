<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines — Vietnamese (vi)
    |--------------------------------------------------------------------------
    |
    | All user-facing validation messages are in Vietnamese.
    | :attribute placeholders are replaced by the 'attributes' array at bottom.
    |
    */

    'accepted' => ':attribute phải được chấp nhận.',
    'active_url' => ':attribute không phải là URL hợp lệ.',
    'after' => ':attribute phải là ngày sau :date.',
    'after_or_equal' => ':attribute phải là ngày sau hoặc bằng :date.',
    'alpha' => ':attribute chỉ được chứa chữ cái.',
    'alpha_dash' => ':attribute chỉ được chứa chữ cái, số, dấu gạch ngang và gạch dưới.',
    'alpha_num' => ':attribute chỉ được chứa chữ cái và số.',
    'array' => ':attribute phải là một mảng.',
    'before' => ':attribute phải là ngày trước :date.',
    'before_or_equal' => ':attribute phải là ngày trước hoặc bằng :date.',
    'between' => [
        'numeric' => ':attribute phải nằm trong khoảng :min - :max.',
        'file' => ':attribute phải có dung lượng từ :min đến :max kilobytes.',
        'string' => ':attribute phải có độ dài từ :min đến :max ký tự.',
        'array' => ':attribute phải có từ :min đến :max phần tử.',
    ],
    'boolean' => ':attribute phải là true hoặc false.',
    'confirmed' => ':attribute xác nhận không khớp.',
    'date' => ':attribute không phải là ngày hợp lệ.',
    'date_equals' => ':attribute phải là ngày bằng :date.',
    'date_format' => ':attribute không đúng định dạng :format.',
    'different' => ':attribute và :other phải khác nhau.',
    'digits' => ':attribute phải có :digits chữ số.',
    'digits_between' => ':attribute phải có từ :min đến :max chữ số.',
    'dimensions' => ':attribute có kích thước ảnh không hợp lệ.',
    'distinct' => ':attribute có giá trị bị trùng lặp.',
    'email' => ':attribute không đúng định dạng email.',
    'ends_with' => ':attribute phải kết thúc bằng một trong các giá trị: :values.',
    'exists' => ':attribute được chọn không hợp lệ.',
    'file' => ':attribute phải là một tập tin.',
    'filled' => ':attribute không được để trống.',
    'gt' => [
        'numeric' => ':attribute phải lớn hơn :value.',
        'file' => ':attribute phải có dung lượng lớn hơn :value kilobytes.',
        'string' => ':attribute phải có nhiều hơn :value ký tự.',
        'array' => ':attribute phải có nhiều hơn :value phần tử.',
    ],
    'gte' => [
        'numeric' => ':attribute phải lớn hơn hoặc bằng :value.',
        'file' => ':attribute phải có dung lượng lớn hơn hoặc bằng :value kilobytes.',
        'string' => ':attribute phải có ít nhất :value ký tự.',
        'array' => ':attribute phải có ít nhất :value phần tử.',
    ],
    'image' => ':attribute phải là ảnh.',
    'in' => ':attribute được chọn không hợp lệ.',
    'in_array' => ':attribute không tồn tại trong :other.',
    'integer' => ':attribute phải là số nguyên.',
    'ip' => ':attribute phải là địa chỉ IP hợp lệ.',
    'ipv4' => ':attribute phải là địa chỉ IPv4 hợp lệ.',
    'ipv6' => ':attribute phải là địa chỉ IPv6 hợp lệ.',
    'json' => ':attribute phải là chuỗi JSON hợp lệ.',
    'lt' => [
        'numeric' => ':attribute phải nhỏ hơn :value.',
        'file' => ':attribute phải có dung lượng nhỏ hơn :value kilobytes.',
        'string' => ':attribute phải có ít hơn :value ký tự.',
        'array' => ':attribute phải có ít hơn :value phần tử.',
    ],
    'lte' => [
        'numeric' => ':attribute phải nhỏ hơn hoặc bằng :value.',
        'file' => ':attribute phải có dung lượng nhỏ hơn hoặc bằng :value kilobytes.',
        'string' => ':attribute phải có tối đa :value ký tự.',
        'array' => ':attribute phải có tối đa :value phần tử.',
    ],
    'max' => [
        'numeric' => ':attribute không được lớn hơn :max.',
        'file' => ':attribute không được vượt quá :max kilobytes.',
        'string' => ':attribute không được vượt quá :max ký tự.',
        'array' => ':attribute không được có nhiều hơn :max phần tử.',
    ],
    'mimes' => ':attribute phải là tập tin có định dạng: :values.',
    'mimetypes' => ':attribute phải là tập tin có định dạng: :values.',
    'min' => [
        'numeric' => ':attribute phải tối thiểu :min.',
        'file' => ':attribute phải có dung lượng tối thiểu :min kilobytes.',
        'string' => ':attribute phải có ít nhất :min ký tự.',
        'array' => ':attribute phải có ít nhất :min phần tử.',
    ],
    'not_in' => ':attribute được chọn không hợp lệ.',
    'not_regex' => ':attribute có định dạng không hợp lệ.',
    'numeric' => ':attribute phải là số.',
    'password' => 'Mật khẩu không chính xác.',
    'present' => ':attribute phải có mặt.',
    'regex' => ':attribute có định dạng không hợp lệ.',
    'required' => ':attribute không được để trống.',
    'required_if' => ':attribute không được để trống khi :other là :value.',
    'required_unless' => ':attribute không được để trống trừ khi :other thuộc :values.',
    'required_with' => ':attribute không được để trống khi :values có mặt.',
    'required_with_all' => ':attribute không được để trống khi :values có mặt.',
    'required_without' => ':attribute không được để trống khi :values không có mặt.',
    'required_without_all' => ':attribute không được để trống khi không có :values nào có mặt.',
    'same' => ':attribute và :other phải giống nhau.',
    'size' => [
        'numeric' => ':attribute phải bằng :size.',
        'file' => ':attribute phải có dung lượng :size kilobytes.',
        'string' => ':attribute phải có :size ký tự.',
        'array' => ':attribute phải chứa :size phần tử.',
    ],
    'starts_with' => ':attribute phải bắt đầu bằng một trong các giá trị: :values.',
    'string' => ':attribute phải là chuỗi ký tự.',
    'timezone' => ':attribute phải là múi giờ hợp lệ.',
    'unique' => ':attribute đã được sử dụng.',
    'uploaded' => ':attribute tải lên thất bại.',
    'url' => ':attribute không phải là URL hợp lệ.',
    'uuid' => ':attribute phải là UUID hợp lệ.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Messages
    |--------------------------------------------------------------------------
    */
    'custom' => [
        'phone' => [
            'regex' => 'Số điện thoại không đúng định dạng (VD: 0901234567).',
        ],
        'payment_method' => [
            'in' => 'Phương thức thanh toán không hợp lệ.',
        ],
        'items.*.quantity' => [
            'min' => 'Số lượng phải ít nhất là 1.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribute Name Translations
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        'customer_name' => 'Họ và tên',
        'name' => 'Họ và tên',
        'phone' => 'Số điện thoại',
        'email' => 'Email',
        'address' => 'Địa chỉ',
        'city' => 'Tỉnh/Thành phố',
        'district' => 'Quận/Huyện',
        'ward' => 'Phường/Xã',
        'notes' => 'Ghi chú',
        'note' => 'Ghi chú',
        'password' => 'Mật khẩu',
        'password_confirmation' => 'Xác nhận mật khẩu',
        'quantity' => 'Số lượng',
        'coupon_code' => 'Mã giảm giá',
    ],

];
