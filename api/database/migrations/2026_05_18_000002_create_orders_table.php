<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignIfExists('order_items', 'order_items_order_id_foreign');

        Schema::dropIfExists('orders');

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('address_extra')->nullable();
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('courier_name')->nullable();
            $table->string('tracking_number')->nullable();
            $table->enum('cancelled_by', ['seller', 'customer', 'admin'])->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['store_id', 'status']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('id');
            }
        });

        DB::statement('ALTER TABLE `order_items` MODIFY `order_id` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `order_items` ADD CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $this->dropForeignIfExists('order_items', 'order_items_order_id_foreign');

        Schema::dropIfExists('orders');
    }

    private function dropForeignIfExists(string $table, string $foreignKey): void
    {
        try {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKey}`");
        } catch (\Throwable) {
        }
    }
};
