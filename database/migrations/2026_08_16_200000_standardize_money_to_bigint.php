<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * S3: Standardize money fields to unsignedBigInteger (VND, integer only, no cents).
     *
     * IMPORTANT: This migration converts existing float/decimal values to integers.
     * Any fractional cents (unlikely for VND) will be truncated.
     * Run this during a maintenance window and back up data first.
     *
     * Affected tables: orders, order_items
     */
    public function up(): void
    {
        // orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal')->default(0)->change();
            $table->unsignedBigInteger('discount_amount')->default(0)->change();
            $table->unsignedBigInteger('shipping_fee')->default(0)->change();
            $table->unsignedBigInteger('total_amount')->default(0)->change();
        });

        // order_items table
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('price_at_purchase')->default(0)->change();

            // subtotal was missing from the base migration but is written by OrderService.
            // Add it if absent, then change() to bigint.
            if (! Schema::hasColumn('order_items', 'subtotal')) {
                $table->unsignedBigInteger('subtotal')->default(0)->after('price_at_purchase');
            } else {
                $table->unsignedBigInteger('subtotal')->default(0)->change();
            }
        });

        // products table
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('price')->default(0)->change();
        });

        // product_variants table (if price column exists)
        if (Schema::hasColumn('product_variants', 'price')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->unsignedBigInteger('price')->default(0)->change();
            });
        }

        // coupons table: value is the discount amount in VND for 'fixed' type
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedBigInteger('value')->default(0)->change();
            $table->unsignedBigInteger('min_order_amount')->default(0)->change();
        });
    }

    public function down(): void
    {
        // Revert to decimal — note: no data loss since integers fit in decimal(15,2)
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 2)->default(0)->change();
            $table->decimal('discount_amount', 15, 2)->default(0)->change();
            $table->decimal('shipping_fee', 15, 2)->default(0)->change();
            $table->decimal('total_amount', 15, 2)->default(0)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('price_at_purchase', 15, 2)->default(0)->change();
            if (Schema::hasColumn('order_items', 'subtotal')) {
                $table->decimal('subtotal', 15, 2)->default(0)->change();
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->default(0)->change();
        });

        if (Schema::hasColumn('product_variants', 'price')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->decimal('price', 15, 2)->default(0)->change();
            });
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->decimal('value', 15, 2)->default(0)->change();
            $table->decimal('min_order_amount', 15, 2)->default(0)->change();
        });
    }
};
