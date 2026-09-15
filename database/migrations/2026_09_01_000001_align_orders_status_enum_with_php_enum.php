<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Align orders.status ENUM with OrderStatus PHP enum values.
 *
 * Original migration used 'shipping' but OrderStatus::Shipped->value = 'shipped'.
 * MySQL strict mode (STRICT_ALL_TABLES) throws SQLSTATE[01000] Warning 1265
 * (Data truncated) when inserting 'shipped' into the old ENUM definition.
 *
 * This migration:
 * 1. Updates existing rows: 'shipping' → 'shipped'
 * 2. Alters the ENUM column to the aligned values
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Migrate existing data (safe for both MySQL and SQLite)
        DB::table('orders')->where('status', 'shipping')->update(['status' => 'shipped']);

        // Step 2: ALTER TABLE MODIFY COLUMN is MySQL-only syntax.
        // SQLite (used in :memory: tests with RefreshDatabase) does not support it
        // and already has no ENUM enforcement — so we skip it there.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM(
                'pending',
                'confirmed',
                'processing',
                'shipped',
                'delivered',
                'cancelled'
            ) NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        // Revert shipped → shipping for rollback safety
        DB::table('orders')->where('status', 'shipped')->update(['status' => 'shipping']);
        DB::table('orders')->where('status', 'processing')->update(['status' => 'confirmed']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM(
                'pending',
                'confirmed',
                'shipping',
                'delivered',
                'cancelled'
            ) NOT NULL DEFAULT 'pending'");
        }
    }
};
