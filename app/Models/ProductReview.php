<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    protected $fillable = [
        'product_id',
        'customer_id',
        'rating',
        'comment',
        'status',
        'verified_purchase',
        'helpful_count',
        'not_helpful_count',
        'images',
        'pros',
        'cons',
        'moderation_note',
        'moderated_by',
        'moderated_at',
        'seller_response',
        'seller_responded_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'verified_purchase' => 'boolean',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
        'images' => 'array',
        'pros' => 'array',
        'cons' => 'array',
        'moderated_at' => 'datetime',
        'seller_responded_at' => 'datetime',
    ];

    protected $appends = [
        'helpful_percentage',
        'has_seller_response',
        'is_approved',
        'is_pending',
        'is_rejected',
        'is_flagged',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * Helpful percentage.
     */
    public function getHelpfulPercentageAttribute(): int
    {
        $total = $this->helpful_count + $this->not_helpful_count;

        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->helpful_count / $total) * 100);
    }

    /**
     * Check if review has seller response.
     */
    public function getHasSellerResponseAttribute(): bool
    {
        return ! empty($this->seller_response);
    }

    /**
     * Status checks.
     */
    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'approved';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsRejectedAttribute(): bool
    {
        return $this->status === 'rejected';
    }

    public function getIsFlaggedAttribute(): bool
    {
        return $this->status === 'flagged';
    }

    /**
     * Vote helpful.
     */
    public function voteHelpful(): void
    {
        $this->increment('helpful_count');
    }

    /**
     * Vote not helpful.
     */
    public function voteNotHelpful(): void
    {
        $this->increment('not_helpful_count');
    }

    /**
     * Approve the review.
     */
    public function approve(?int $moderatorId = null): void
    {
        $this->update([
            'status' => 'approved',
            'moderated_by' => $moderatorId,
            'moderated_at' => now(),
        ]);
    }

    /**
     * Reject the review.
     */
    public function reject(?int $moderatorId = null, ?string $note = null): void
    {
        $this->update([
            'status' => 'rejected',
            'moderated_by' => $moderatorId,
            'moderated_at' => now(),
            'moderation_note' => $note,
        ]);
    }

    /**
     * Flag the review.
     */
    public function flag(?int $moderatorId = null, ?string $note = null): void
    {
        $this->update([
            'status' => 'flagged',
            'moderated_by' => $moderatorId,
            'moderated_at' => now(),
            'moderation_note' => $note,
        ]);
    }

    /**
     * Add seller response.
     */
    public function addSellerResponse(string $response): void
    {
        $this->update([
            'seller_response' => $response,
            'seller_responded_at' => now(),
        ]);
    }

    /**
     * Get rating stars as array for display.
     */
    public function getStarsAttribute(): array
    {
        $stars = [];
        for ($i = 1; $i <= 5; $i++) {
            $stars[] = [
                'value' => $i,
                'filled' => $i <= $this->rating,
            ];
        }

        return $stars;
    }

    /**
     * Get formatted date.
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->created_at->format('d/m/Y');
    }

    /**
     * Get short excerpt of comment.
     */
    public function getExcerptAttribute(int $length = 150): string
    {
        return strlen($this->comment) > $length
            ? substr($this->comment, 0, $length).'...'
            : $this->comment;
    }
}
