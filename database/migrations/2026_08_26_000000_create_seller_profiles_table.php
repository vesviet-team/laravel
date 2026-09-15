<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('shop_name');
            $table->string('subdomain')->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active'); // active, pending, suspended
            $table->string('telegram_chat_id')->nullable();

            // Payment (VietQR)
            $table->string('bank_code')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('bank_account_name')->nullable();

            // Shipping
            $table->string('shipping_type')->default('freeship'); // freeship, flat_rate
            $table->bigInteger('shipping_fee')->default(0); // VND

            // Meta
            $table->text('bio')->nullable();
            $table->string('logo_url')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_profiles');
    }
};
