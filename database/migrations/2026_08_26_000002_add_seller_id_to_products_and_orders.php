<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->constrained('seller_profiles')->nullOnDelete();
            $table->boolean('show_on_marketplace')->default(false);
            $table->json('options_json')->nullable();

            // Drop global unique slug and add composite unique
            $table->dropUnique('products_slug_unique');
            $table->unique(['seller_id', 'slug'], 'products_seller_slug_unique');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->constrained('seller_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn('seller_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_seller_slug_unique');
            $table->unique('slug', 'products_slug_unique');

            $table->dropForeign(['seller_id']);
            $table->dropColumn(['seller_id', 'show_on_marketplace', 'options_json']);
        });
    }
};
