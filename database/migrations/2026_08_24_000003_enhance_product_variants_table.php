<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enhance product_variants table with pricing, position, barcode, and attributes.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Pricing
            if (! Schema::hasColumn('product_variants', 'compare_at_price')) {
                $table->unsignedBigInteger('compare_at_price')->nullable()->after('price')
                    ->comment('Original price for sale/discount display (VND).');
            }

            // Position for ordering variants
            if (! Schema::hasColumn('product_variants', 'position')) {
                $table->unsignedInteger('position')->default(0)->after('is_active')
                    ->comment('Display order for variant options.');
            }

            // Barcode/Identification
            if (! Schema::hasColumn('product_variants', 'barcode')) {
                $table->string('barcode')->nullable()->unique()->after('sku')
                    ->comment('EAN/UPC/ISBN barcode for inventory systems.');
            }

            // Physical attributes (override product if > 0)
            if (! Schema::hasColumn('product_variants', 'weight')) {
                $table->unsignedInteger('weight')->default(0)->after('position')
                    ->comment('Weight in grams. Overrides product weight if > 0.');
            }
            if (! Schema::hasColumn('product_variants', 'length')) {
                $table->unsignedInteger('length')->default(0)->after('weight')
                    ->comment('Length in cm.');
            }
            if (! Schema::hasColumn('product_variants', 'width')) {
                $table->unsignedInteger('width')->default(0)->after('length')
                    ->comment('Width in cm.');
            }
            if (! Schema::hasColumn('product_variants', 'height')) {
                $table->unsignedInteger('height')->default(0)->after('width')
                    ->comment('Height in cm.');
            }

            // Inventory
            if (! Schema::hasColumn('product_variants', 'low_stock_threshold')) {
                $table->unsignedInteger('low_stock_threshold')->default(5)->after('stock')
                    ->comment('Threshold for low stock notifications.');
            }

            // Variant-specific attributes (color, size, material, etc.)
            if (! Schema::hasColumn('product_variants', 'option_values')) {
                $table->json('option_values')->nullable()->after('attributes_json')
                    ->comment('Selected option values e.g. {"color": "red", "size": "M"}.');
            }

            // Availability
            if (! Schema::hasColumn('product_variants', 'is_purchasable')) {
                $table->boolean('is_purchasable')->default(true)->after('is_active')
                    ->comment('Whether this variant can be purchased.');
            }

            // Indexes
            if (! Schema::hasIndex('product_variants', 'variants_product_active_idx')) {
                $table->index(['product_id', 'is_active', 'is_purchasable'], 'variants_product_active_idx');
            }
            if (! Schema::hasIndex('product_variants', 'variants_barcode_idx')) {
                $table->index('barcode', 'variants_barcode_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasIndex('product_variants', 'variants_product_active_idx')) {
                $table->dropIndex('variants_product_active_idx');
            }
            if (Schema::hasIndex('product_variants', 'variants_barcode_idx')) {
                $table->dropIndex('variants_barcode_idx');
            }

            $columnsToDrop = [
                'compare_at_price',
                'position',
                'barcode',
                'weight',
                'length',
                'width',
                'height',
                'low_stock_threshold',
                'option_values',
                'is_purchasable',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('product_variants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
