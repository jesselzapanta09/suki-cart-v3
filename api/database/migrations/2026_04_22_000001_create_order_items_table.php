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
        $this->dropForeignIfExists('order_items', 'order_items_user_id_foreign');
        $this->dropForeignIfExists('order_items', 'order_items_location_id_foreign');

        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->text('message')->nullable();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
                $table->integer('quantity');
                $table->decimal('price', 12, 2);
                $table->timestamps();
            });

            return;
        }

        $this->dropIndexIfExists('order_items', 'order_items_user_id_status_index');
        $this->dropIndexIfExists('order_items', 'order_items_order_id_status_index');
        $this->dropIndexIfExists('order_items', 'order_items_checkout_no_unique');

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('id');
            }

            $columnsToDrop = [
                'checkout_no',
                'user_id',
                'location_id',
                'address_extra',
                'shipping_cost',
                'status',
                'courier_name',
                'tracking_number',
                'cancelled_by',
                'cancellation_reason',
                'cancelled_at',
            ];

            $existingColumns = array_values(array_filter($columnsToDrop, fn ($column) => Schema::hasColumn('order_items', $column)));

            if ($existingColumns !== []) {
                $table->dropColumn($existingColumns);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }

    private function dropForeignIfExists(string $table, string $foreignKey): void
    {
        try {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKey}`");
        } catch (\Throwable) {
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        } catch (\Throwable) {
        }
    }
};
