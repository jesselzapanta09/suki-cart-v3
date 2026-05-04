<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Converts push_subscriptions table from VAPID format to FCM format.
     * For SQLite compatibility, we recreate the table instead of altering columns.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support dropping columns easily, so we recreate the table
            DB::statement('PRAGMA foreign_keys = OFF');

            // Create new table with FCM schema
            Schema::create('push_subscriptions_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('device_token')->unique();
                $table->string('device_type')->nullable(); // 'web', 'android', 'ios'
                $table->string('device_name')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });

            // Copy existing data (device_token will be null for old VAPID entries)
            // We'll let new registrations set the device_token
            // Old VAPID subscriptions won't transfer over (intentional, as tokens are incompatible)

            // Drop old table
            Schema::drop('push_subscriptions');

            // Rename new table
            DB::statement('ALTER TABLE push_subscriptions_new RENAME TO push_subscriptions');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            // For MySQL and other databases, use standard ALTER TABLE
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->dropUnique('push_subscriptions_endpoint_unique');
                $table->dropColumn(['endpoint', 'public_key', 'auth_token', 'user_agent']);
                
                $table->string('device_token')->unique()->after('user_id');
                $table->string('device_type')->nullable()->after('device_token');
                $table->string('device_name')->nullable()->after('device_type');
                $table->timestamp('last_used_at')->nullable()->after('device_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            // Recreate old VAPID schema
            Schema::create('push_subscriptions_old', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('endpoint');
                $table->string('public_key');
                $table->string('auth_token');
                $table->string('user_agent')->nullable();
                $table->timestamps();
                $table->unique('endpoint', 'push_subscriptions_endpoint_unique');
                $table->index('user_id');
            });

            // Drop FCM table
            Schema::drop('push_subscriptions');

            // Rename back
            DB::statement('ALTER TABLE push_subscriptions_old RENAME TO push_subscriptions');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->dropUnique('push_subscriptions_device_token_unique');
                $table->dropColumn(['device_token', 'device_type', 'device_name', 'last_used_at']);
                
                $table->text('endpoint')->after('user_id');
                $table->string('public_key')->after('endpoint');
                $table->string('auth_token')->after('public_key');
                $table->string('user_agent')->nullable()->after('auth_token');
                
                $table->unique('endpoint', 'push_subscriptions_endpoint_unique');
            });
        }
    }
};
