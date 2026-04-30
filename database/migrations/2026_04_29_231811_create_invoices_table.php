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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Customer::class, 'customer_id')->index()->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->date('issue_date');
            $table->decimal('total_amount', 10, 2);
            $table->text('description')->nullable();
            $table->enum('status', \App\Constants\InvoiceStatus::cases())->default(\App\Constants\InvoiceStatus::DRAFT);
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->foreignIdFor(\App\Models\User::class, 'created_by')->index()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
