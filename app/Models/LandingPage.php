<?php

namespace App\Models;

use App\Observers\LandingPageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([LandingPageObserver::class])]
class LandingPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'product_id',
        'content',
        'is_active',
        'seo_title',
        'seo_description',
        'facebook_pixel_id',
        'tiktok_pixel_id',
        'urgency_end_time',
        'urgency_fake_views',
        'combo_rules_json',
        'features_json',
        'header_logo_url',
        'header_cta_text',
        'footer_content',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urgency_end_time' => 'datetime',
        'combo_rules_json' => 'array',
        'features_json' => 'array',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Return parsed combo rules as array of ['id', 'name', 'price'].
     */
    public function comboRules(): array
    {
        return is_array($this->combo_rules_json) ? $this->combo_rules_json : [];
    }

    /**
     * Return parsed features as flat string array.
     * Handles both Repeater format [{text: "..."}] and legacy flat ["..."].
     */
    public function featuresList(): array
    {
        $raw = $this->features_json;
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(fn ($item) => is_array($item) ? ($item['text'] ?? '') : $item)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Whether this landing page has an active urgency countdown.
     */
    public function hasActiveUrgency(): bool
    {
        return $this->urgency_end_time !== null
            && $this->urgency_end_time->isFuture();
    }

    /**
     * Whether the linked product is in stock.
     */
    public function isInStock(): bool
    {
        if (! $this->product) {
            return true; // no product linked — allow order
        }

        return ($this->product->stock ?? 0) > 0;
    }
}
