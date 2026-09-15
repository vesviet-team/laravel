<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Traits\BelongsToSeller;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderHistory extends Model
{
    use BelongsToSeller, HasFactory;

    protected $fillable = [
        'order_id',
        'seller_id',
        'user_id',
        'old_status',
        'new_status',
        'note',
    ];

    protected $casts = [
        'old_status' => OrderStatus::class,
        'new_status' => OrderStatus::class,
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
