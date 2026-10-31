<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_trackers', function (Blueprint $t) {
            $t->string('public_tracking_code', 16)->nullable()->unique();
            $t->foreignId('public_sale_order_id')->nullable()->unique()->constrained('sale_orders')->restrictOnDelete();
            $t->string('delivery_status', 32)->nullable();
            $t->date('public_estimated_delivery_at')->nullable();
            $t->timestamp('public_delivered_at')->nullable();
            $t->foreignId('public_delivered_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('order_tracker_activities', function (Blueprint $t) {
            $t->string('public_message')->nullable();
        });
        Schema::create('payment_links', function (Blueprint $t) {
            $t->id();
            $t->string('public_token', 64)->unique();
            $t->string('code')->unique();
            $t->foreignId('customer_id')->constrained('entities')->restrictOnDelete();
            $t->foreignId('sale_order_id')->nullable()->constrained('sale_orders')->restrictOnDelete();
            $t->foreignId('account_receivable_id')->constrained('account_receivables')->restrictOnDelete();
            $t->foreignId('payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $t->char('currency', 3)->default('USD');
            $t->decimal('exchange_rate', 16, 6);
            $t->decimal('total', 15, 2);
            $t->string('description', 255);
            $t->text('internal_note')->nullable();
            $t->string('status', 24)->default('pending')->index();
            $t->dateTime('expires_at');
            $t->string('provider')->default('paypal');
            $t->string('provider_order_id')->nullable()->unique();
            $t->string('provider_capture_id')->nullable()->unique();
            $t->string('provider_status')->nullable();
            $t->string('review_reason')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('payment_link_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_link_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('part_number')->nullable();
            $t->string('description');
            $t->unsignedInteger('quantity');
            $t->decimal('unit_price', 15, 2);
            $t->decimal('line_total', 15, 2);
            $t->timestamps();
        });
        Schema::create('payment_provider_events', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 20);
            $t->string('event_id', 128);
            $t->string('event_type', 128);
            $t->char('payload_hash', 64);
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        // Additive financial migration is deliberately not destructive.
        throw new RuntimeException('Esta migración financiera requiere un plan explícito de reversión.');
    }
};
