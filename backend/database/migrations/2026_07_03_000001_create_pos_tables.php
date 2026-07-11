<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lightweight lookup table used by the product form and filters.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Core catalog table. Frequently queried fields stay relational; flexible UI fields live in `data`.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('category')->nullable()->index();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('unit')->nullable();
            $table->decimal('stock', 12, 3)->default(0);
            $table->decimal('low_stock_alert', 12, 3)->default(0);
            $table->json('data')->nullable();
            $table->timestamps();
        });

        // Small generic counter table, currently used for OR number sequencing.
        Schema::create('counters', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();
        });

        // Completed or posted POS sales.
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('or_number')->nullable()->unique();
            $table->string('date')->index();
            $table->string('time');
            $table->foreignId('cashier_id')->nullable()->index();
            $table->string('cashier_name')->nullable();
            $table->string('payment_method')->nullable()->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('status')->nullable()->index();
            $table->json('items')->nullable();
            $table->json('customer')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        // Inventory adjustments such as stock-in and manual corrections.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->index();
            $table->string('product_name')->nullable();
            $table->string('type')->nullable()->index();
            $table->decimal('qty', 12, 3)->default(0);
            $table->json('data')->nullable();
            $table->timestamps();
        });

        // Global store profile and operational settings.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Expense entries used by reporting and cash-flow review.
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('date')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('category')->nullable()->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->json('data')->nullable();
            $table->timestamps();
        });

        // Human-readable audit trail for important user actions.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user')->nullable();
            $table->text('action');
            $table->string('timestamp')->nullable();
            $table->timestamps();
        });

        // Pending/in-progress customer orders tracked before they become final transactions.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending')->index();
            $table->string('date')->index();
            $table->string('time');
            $table->foreignId('cashier_id')->nullable()->index();
            $table->string('cashier_name')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->json('items')->nullable();
            $table->json('customer')->nullable();
            $table->json('edit_lock')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        // Fine-grained order edit history, especially useful when multiple staff touch one order.
        Schema::create('order_edit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->index();
            $table->string('edited_at_text');
            $table->foreignId('edited_by_id')->nullable()->index();
            $table->string('edited_by_name')->nullable();
            $table->json('changed_fields')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });

        // Credit ledger tied to transactions and incremental payments.
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->index();
            $table->string('or_number')->nullable()->index();
            $table->string('customer_name')->nullable()->index();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('remaining_balance', 12, 2)->default(0);
            $table->string('status')->default('unpaid')->index();
            $table->string('start_date')->nullable();
            $table->string('due_date')->nullable();
            $table->json('payments')->nullable();
            $table->json('items')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Drop in reverse dependency order for safer rollbacks.
        Schema::dropIfExists('credits');
        Schema::dropIfExists('order_edit_logs');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('counters');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
