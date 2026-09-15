<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enhance product_reviews table with helpfulness, verified purchase, images, moderation.
     */
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            // Verified purchase
            $table->boolean('verified_purchase')->default(false)->after('status')
                ->comment('Whether reviewer purchased and received this product.');

            // Helpfulness voting
            $table->unsignedInteger('helpful_count')->default(0)->after('verified_purchase')
                ->comment('Number of helpful votes.');
            $table->unsignedInteger('not_helpful_count')->default(0)->after('helpful_count')
                ->comment('Number of not helpful votes.');

            // Review content enhancements
            $table->json('images')->nullable()->after('comment')
                ->comment('Array of uploaded image URLs for the review.');
            $table->json('pros')->nullable()->after('images')
                ->comment('Pros listed in the review.');
            $table->json('cons')->nullable()->after('pros')
                ->comment('Cons listed in the review.');

            // Moderation
            $table->text('moderation_note')->nullable()->after('status')
                ->comment('Admin note for moderation decision.');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete()->after('moderation_note')
                ->comment('Admin user who moderated this review.');
            $table->timestamp('moderated_at')->nullable()->after('moderated_by')
                ->comment('When the review was moderated.');

            // Response from seller
            $table->text('seller_response')->nullable()->after('moderated_at')
                ->comment('Seller/merchant response to the review.');
            $table->timestamp('seller_responded_at')->nullable()->after('seller_response')
                ->comment('When seller responded.');

            // Status enum update (pending, approved, rejected, flagged)
            // Note: status column already exists as string

            // Indexes
            $table->index(['product_id', 'status', 'verified_purchase'], 'reviews_product_status_verified_idx');
            $table->index(['customer_id', 'product_id'], 'reviews_customer_product_idx');
            $table->index('helpful_count', 'reviews_helpful_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_product_status_verified_idx');
            $table->dropIndex('reviews_customer_product_idx');
            $table->dropIndex('reviews_helpful_idx');

            $table->dropColumn([
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
            ]);
        });
    }
};
