<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('avatar');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('date_of_birth');
            $table->string('referral_code')->unique()->nullable()->after('gender');
            $table->unsignedBigInteger('referred_by')->nullable()->after('referral_code');
            $table->integer('loyalty_points')->default(0)->after('referred_by');
            $table->timestamp('email_verified_at')->nullable()->after('loyalty_points');
            $table->json('notification_preferences')->nullable()->after('email_verified_at');
            $table->json('privacy_consent')->nullable()->after('notification_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'avatar',
                'date_of_birth',
                'gender',
                'referral_code',
                'referred_by',
                'loyalty_points',
                'email_verified_at',
                'notification_preferences',
                'privacy_consent',
            ]);
        });
    }
};
