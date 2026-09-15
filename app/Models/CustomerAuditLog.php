<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'action',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public static function log(string $action, ?string $description = null, array $oldValues = [], array $newValues = []): self
    {
        $customer = auth('customer')->user();

        if (! $customer) {
            return null;
        }

        return self::create([
            'customer_id' => $customer->id,
            'action' => $action,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
