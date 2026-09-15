<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAuditLog;
use Illuminate\Http\Request;

class AuditLogService
{
    public function log(
        Customer $customer,
        string $action,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
        ?Request $request = null
    ): ?CustomerAuditLog {
        return CustomerAuditLog::create([
            'customer_id' => $customer->id,
            'action' => $action,
            'description' => $description,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request?->ip() ?? request()->ip(),
            'user_agent' => $request?->userAgent() ?? request()->userAgent(),
        ]);
    }

    public function logLogin(Customer $customer, bool $success = true, ?Request $request = null): void
    {
        $this->log(
            $customer,
            'login',
            $success ? 'Đăng nhập thành công' : 'Đăng nhập thất bại',
            [],
            ['success' => $success],
            $request
        );
    }

    public function logLogout(Customer $customer, ?Request $request = null): void
    {
        $this->log($customer, 'logout', 'Đăng xuất', [], [], $request);
    }

    public function logPasswordChange(Customer $customer, ?Request $request = null): void
    {
        $this->log($customer, 'password_change', 'Đổi mật khẩu', [], [], $request);
    }

    public function logProfileUpdate(Customer $customer, array $oldValues, array $newValues, ?Request $request = null): void
    {
        $this->log($customer, 'profile_update', 'Cập nhật hồ sơ', $oldValues, $newValues, $request);
    }

    public function logTwoFactorEnable(Customer $customer, ?Request $request = null): void
    {
        $this->log($customer, 'two_factor_enable', 'Bật xác thực hai yếu tố', [], [], $request);
    }

    public function logTwoFactorDisable(Customer $customer, ?Request $request = null): void
    {
        $this->log($customer, 'two_factor_disable', 'Tắt xác thực hai yếu tố', [], [], $request);
    }

    public function logAddressCreate(Customer $customer, array $values, ?Request $request = null): void
    {
        $this->log($customer, 'address_create', 'Thêm địa chỉ mới', [], $values, $request);
    }

    public function logAddressUpdate(Customer $customer, array $oldValues, array $newValues, ?Request $request = null): void
    {
        $this->log($customer, 'address_update', 'Cập nhật địa chỉ', $oldValues, $newValues, $request);
    }

    public function logAddressDelete(Customer $customer, array $oldValues, ?Request $request = null): void
    {
        $this->log($customer, 'address_delete', 'Xóa địa chỉ', $oldValues, [], $request);
    }

    public function logDataExport(Customer $customer, ?Request $request = null): void
    {
        $this->log($customer, 'data_export', 'Xuất dữ liệu cá nhân (GDPR)', [], [], $request);
    }

    public function logAccountDeletion(Customer $customer, ?Request $request = null): void
    {
        $this->log($customer, 'account_deletion', 'Xóa tài khoản (GDPR)', [], [], $request);
    }

    public function getAuditLogs(Customer $customer, int $perPage = 20)
    {
        return $customer->auditLogs()->latest()->paginate($perPage);
    }
}
