<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enhance products table with pricing, publishing, SEO, and physical attributes.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Pricing
            $table->unsignedBigInteger('compare_at_price')->nullable()->after('price')
                ->comment('Original price for sale/discount display (VND).');

            // Publishing workflow
            $table->timestamp('published_at')->nullable()->after('status')
                ->comment('When the product was first published.');

            // Physical attributes for shipping
            $table->unsignedInteger('length')->default(0)->after('weight')
                ->comment('Length in cm.');
            $table->unsignedInteger('width')->default(0)->after('length')
                ->comment('Width in cm.');
            $table->unsignedInteger('height')->default(0)->after('width')
                ->comment('Height in cm.');

            // Inventory tracking
            $table->unsignedInteger('low_stock_threshold')->default(5)->after('stock')
                ->comment('Threshold for low stock notifications.');

            // SEO & Meta
            $table->string('meta_title')->nullable()->after('seo_description')
                ->comment('Override SEO title for product page.');
            $table->text('meta_description')->nullable()->after('meta_title')
                ->comment('Override SEO meta description.');
            $table->string('meta_keywords')->nullable()->after('meta_description')
                ->comment('SEO keywords (comma-separated).');

            // Structured data enhancements
            $table->json('structured_data')->nullable()->after('attributes_json')
                ->comment('Additional Schema.org structured data fields.');

            // Visibility & search
            $table->boolean('is_visible')->default(true)->after('is_featured')
                ->comment('Whether product is visible in catalog/search.');
            $table->boolean('is_purchasable')->default(true)->after('is_visible')
                ->comment('Whether product can be added to cart.');
            $table->json('tags')->nullable()->after('attributes_json')
                ->comment('Flexible tags for filtering (color, material, style, etc.).');

            // Performance indexes
            $table->index(['status', 'is_visible', 'is_featured'], 'products_catalog_idx');
            $table->index(['category_id', 'status', 'is_visible'], 'products_category_status_idx');
            $table->index('published_at', 'products_published_at_idx');
            $table->index('slug', 'products_slug_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_catalog_idx');
            $table->dropIndex('products_category_status_idx');
            $table->dropIndex('products_published_at_idx');
            $table->dropIndex('products_slug_idx');

            $table->dropColumn([
                'compare_at_price',
                'published_at',
                'length',
                'width',
                'height',
                'low_stock_threshold',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'structured_data',
                'is_visible',
                'is_purchasable',
                'tags',
            ]);
        });
    }
};
