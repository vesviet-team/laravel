<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Models\Tenant;

class SellerProfile extends Tenant implements HasName
{
    use HasFactory, SoftDeletes;

    public const RESERVED_SUBDOMAINS = [
        'admin', 'seller', 'app', 'api', 'www',
        'cpanel', 'mail', 'dev', 'staging', 'localhost',
    ];

    protected $fillable = [
        'user_id',
        'shop_name',
        'subdomain',
        'shop_slug',   // Slice 1: path-based storefront slug — Admin-managed via AdminUpdateSellerSlugAction
        'phone',
        'email',
        'status',
        'telegram_chat_id',
        'bank_code',
        'bank_account_no',
        'bank_account_name',
        'shipping_type',
        'shipping_fee',
        'bio',
        'logo_url',
    ];

    protected $casts = [
        'shipping_fee' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SellerPage::class, 'seller_id');
    }

    public function defaultPage(): HasOne
    {
        return $this->hasOne(SellerPage::class, 'seller_id')->latestOfMany();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasCompleteBankInfo(): bool
    {
        return ! empty($this->bank_code)
            && ! empty($this->bank_account_no)
            && ! empty($this->bank_account_name);
    }

    public function generateUniqueSubdomain(string $shopName): string
    {
        $baseSlug = Str::slug($shopName) ?: 'shop';
        $slug = $baseSlug;
        $counter = 1;

        while (static::withTrashed()->where('subdomain', $slug)->exists()
            || in_array($slug, self::RESERVED_SUBDOMAINS, true)) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public function getFilamentName(): string
    {
        return $this->shop_name;
    }
}
