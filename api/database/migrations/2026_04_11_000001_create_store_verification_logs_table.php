<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->enum('action', ['approve', 'reject', 'pending', 'resubmit', 'revert']);
            $table->enum('previous_status', ['pending', 'approved', 'rejected', 'suspended'])->nullable();
            $table->enum('new_status', ['pending', 'approved', 'rejected', 'suspended']);
            $table->text('rejection_reason')->nullable();
            $table->uuid('performed_by')->nullable();
            $table->timestamps();

            $table->foreign('performed_by')->references('uuid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_verification_logs');
    }
};
