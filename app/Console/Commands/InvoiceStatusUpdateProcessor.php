<?php

namespace App\Console\Commands;

use App\Constants\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:invoice-status-update-processor')]
#[Description('Command to move invoices to next status if payment time frame expires')]
class InvoiceStatusUpdateProcessor extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        Invoice::query()->where('status', InvoiceStatus::ISSUED)->where('due_date', '<', now())->chunk(100, function ($invoices) {
            $invoices->each(function ($invoice) {
                $this->info("Processing invoice: {$invoice->invoice_number}");
                $invoice->update(['status' => InvoiceStatus::OVERDUE]);
            });
        });
    }
}
