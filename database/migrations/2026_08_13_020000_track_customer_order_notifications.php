<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_events', function (Blueprint $table): void {
            $table->string('customer_notification_status')->nullable()->after('notes')->index();
            $table->timestamp('customer_notification_queued_at')->nullable()->after('customer_notification_status');
            $table->timestamp('customer_notification_sent_at')->nullable()->after('customer_notification_queued_at');
            $table->timestamp('customer_notification_failed_at')->nullable()->after('customer_notification_sent_at');
            $table->string('customer_notification_error', 500)->nullable()->after('customer_notification_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_status_events', function (Blueprint $table): void {
            $table->dropIndex(['customer_notification_status']);
            $table->dropColumn([
                'customer_notification_status',
                'customer_notification_queued_at',
                'customer_notification_sent_at',
                'customer_notification_failed_at',
                'customer_notification_error',
            ]);
        });
    }
};
