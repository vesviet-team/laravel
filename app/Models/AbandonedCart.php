<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbandonedCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'customer_id',
        'cart_token',
        'items_json',
        'subtotal',
        'step_1_sent_at',
        'step_2_sent_at',
        'incentive_coupon_code',
        'recovered_at',
    ];

    protected $casts = [
        'items_json' => 'array',
        'subtotal' => 'integer',
        'step_1_sent_at' => 'datetime',
        'step_2_sent_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isRecovered(): bool
    {
        return ! is_null($this->recovered_at);
    }
}
