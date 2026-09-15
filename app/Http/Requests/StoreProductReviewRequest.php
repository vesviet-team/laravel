<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\ProductReview;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $customerId = auth('customer')->id();

        if (! $customerId) {
            return false;
        }

        $productId = $this->route('product');

        // Verified Purchase: customer must have a 'delivered' order containing this product.
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
            throw new AuthorizationException('You must purchase and receive this product before reviewing.');
        }

        // Prevent duplicate reviews per product per customer.
        $existingReview = ProductReview::where('product_id', $productId)
            ->where('customer_id', $customerId)
            ->exists();

        if ($existingReview) {
            throw new AuthorizationException('You have already reviewed this product.');
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ];
    }

    public function messages()
    {
        return [
            'authorize' => 'You must purchase and receive this product before reviewing, or you have already reviewed it.',
        ];
    }
}
