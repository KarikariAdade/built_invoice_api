<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Invoice::class, 'invoice_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Product::class, 'product_id')->index()->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->double('unit_price', 10, 2);
            $table->double('amount');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
