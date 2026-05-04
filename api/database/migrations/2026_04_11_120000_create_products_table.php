<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->unsignedBigInteger('store_id');
            $table->string('name');
            $table->text('description');
            $table->unsignedBigInteger('category_id');
            $table->decimal('weight', 10, 4);
            $table->string('dimension');
            $table->json('specs');
            $table->enum('status', ['active', 'draft', 'out_of_stock'])->default('active');
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
