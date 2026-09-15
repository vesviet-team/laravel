<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enhance categories table with image, SEO, hierarchy, and display options.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Display
            $table->string('image_path')->nullable()->after('description')
                ->comment('Category banner/thumbnail image.');
            $table->unsignedInteger('sort_order')->default(0)->after('parent_id')
                ->comment('Display order among siblings.');

            // SEO
            $table->string('meta_title')->nullable()->after('description')
                ->comment('Override SEO title for category page.');
            $table->text('meta_description')->nullable()->after('meta_title')
                ->comment('Override SEO meta description.');
            $table->string('meta_keywords')->nullable()->after('meta_description')
                ->comment('SEO keywords (comma-separated).');

            // Hierarchy
            $table->unsignedInteger('level')->default(0)->after('parent_id')
                ->comment('Depth level in category tree (0 = root).');
            $table->string('path')->nullable()->after('level')
                ->comment('Materialized path for fast ancestor queries (e.g. 1/5/12/).');

            // Visibility
            $table->boolean('is_visible')->default(true)->after('level')
                ->comment('Whether category is visible in navigation.');

            // Structured data
            $table->json('structured_data')->nullable()->after('meta_keywords')
                ->comment('Additional Schema.org structured data fields.');

            // Indexes
            $table->index(['parent_id', 'sort_order'], 'categories_parent_sort_idx');
            $table->index(['is_visible', 'level'], 'categories_visible_level_idx');
            $table->index('path', 'categories_path_idx');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_parent_sort_idx');
            $table->dropIndex('categories_visible_level_idx');
            $table->dropIndex('categories_path_idx');

            $table->dropColumn([
                'image_path',
                'sort_order',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'level',
                'path',
                'is_visible',
                'structured_data',
            ]);
        });
    }
};
