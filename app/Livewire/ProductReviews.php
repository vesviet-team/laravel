<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ProductReviews extends Component
{
    use WithPagination;

    public Product $product;

    public $rating = 5;

    public $comment = '';

    public $pros = '';

    public $cons = '';

    protected $rules = [
        'rating' => 'required|integer|min:1|max:5',
        'comment' => 'nullable|string|max:2000',
        'pros' => 'nullable|string|max:500',
        'cons' => 'nullable|string|max:500',
    ];

    protected $messages = [
        'rating.required' => 'Vui lòng chọn số sao đánh giá.',
        'rating.min' => 'Đánh giá tối thiểu là 1 sao.',
        'rating.max' => 'Đánh giá tối đa là 5 sao.',
        'comment.max' => 'Nhận xét không được vượt quá 2000 ký tự.',
        'pros.max' => 'Ưu điểm không được vượt quá 500 ký tự.',
        'cons.max' => 'Nhược điểm không được vượt quá 500 ký tự.',
    ];

    public function submitReview()
    {
        $this->validate();

        if (! Auth::guard('customer')->check()) {
            $this->dispatch('show-login-modal');

            return;
        }

        $customerId = Auth::guard('customer')->id();
        $productId = $this->product->id;

        // 1. Verified Purchase: customer must have at least one 'delivered' order containing this product.
        $hasPurchased = Order::where('customer_id', $customerId)
            ->where('status', 'delivered')
            ->whereHas('items', function ($query) use ($productId) {
                $query->where('product_id', $productId)
                    ->orWhereHas('productVariant', function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    });
            })
            ->exists();

        if (! $hasPurchased) {
            $this->addError('purchase_required', 'Bạn cần mua và nhận hàng thành công sản phẩm này trước khi gửi đánh giá.');

            return;
        }

        // 2. Prevent duplicate reviews per product per customer.
        $existingReview = ProductReview::where('product_id', $productId)
            ->where('customer_id', $customerId)
            ->exists();

        if ($existingReview) {
            $this->addError('duplicate_review', 'Bạn đã gửi đánh giá cho sản phẩm này rồi.');

            return;
        }

        ProductReview::create([
            'product_id' => $productId,
            'customer_id' => $customerId,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'pros' ? $this->pros : null,
            'cons' ? $this->cons : null,
            'status' => 'pending', // Requires admin approval.
        ]);

        $this->reset(['rating', 'comment', 'pros', 'cons']);

        $this->dispatch('review-submitted', message: 'Đánh giá của bạn đã được gửi thành công và đang chờ ban quản trị kiểm duyệt.');
    }

    public function voteHelpful(int $reviewId)
    {
        if (! Auth::guard('customer')->check()) {
            $this->dispatch('show-login-modal');

            return;
        }

        $review = ProductReview::find($reviewId);
        if ($review) {
            $review->voteHelpful();
            $this->dispatch('review-voted', message: 'Cảm ơn bạn đã bình chọn!');
        }
    }

    public function voteNotHelpful(int $reviewId)
    {
        if (! Auth::guard('customer')->check()) {
            $this->dispatch('show-login-modal');

            return;
        }

        $review = ProductReview::find($reviewId);
        if ($review) {
            $review->voteNotHelpful();
            $this->dispatch('review-voted', message: 'Cảm ơn bạn đã bình chọn!');
        }
    }

    public function render()
    {
        $reviews = ProductReview::where('product_id', $this->product->id)
            ->where('status', 'approved')
            ->with('customer')
            ->latest()
            ->paginate(5, ['*'], 'review-page');

        // Calculate rating distribution
        $ratingStats = ProductReview::where('product_id', $this->product->id)
            ->where('status', 'approved')
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $totalReviews = array_sum($ratingStats);
        $avgRating = $totalReviews > 0
            ? array_sum(array_map(fn ($r, $c) => $r * $c, array_keys($ratingStats), $ratingStats)) / $totalReviews
            : 0;

        return view('livewire.product-reviews', [
            'reviews' => $reviews,
            'ratingStats' => $ratingStats,
            'totalReviews' => $totalReviews,
            'avgRating' => round($avgRating, 1),
        ]);
    }
}
