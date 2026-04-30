<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServices
{
    /**
     * Generates a JSON response based on the provided parameters.

     * @return JsonResponse The generated JSON response.
     */
    public function generateResponse($message, $data, $error_code = null, $type = null): JsonResponse
    {
        if ($type === 'error') {
            return response()->json([
                'message' => $message,
                'data' => $data
            ], $error_code ?? 401);
        }

        return response()->json([
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Generates a log entry on the specified logging channel.
     * @return void
     */
    public function generateLog($channel, $header, $message): void
    {
        Log::channel($channel)->alert($header, $message);
    }

    public function generateLogData($data): array
    {
        return ['message' => $data->getMessage(), 'file' => $data->getFile(), 'line' => $data->getLine()];
    }

    /**
     * Validates and processes invoice item products.
     *
     * This method iterates over the provided items and ensures the associated products exist
     * and have sufficient stock to fulfill the requested quantity. It also assigns a default
     * description to the item if it's missing.
     *
     * @return array A response array containing a type ('success' or 'error') and associated data or message.
     */
    public function runInvoiceItemProductChecks($items): array
    {
        foreach ($items as $key => $item) {

            $product = Product::query()->where('id', $item['product_id'])->first();

            if ($product === null) {
                return ['message' => 'Product for invoice item not found', 'type' => 'error'];
            }

            if ($item['description'] === '' || $item['description'] === null) {
                $item['description'] = $product->description;
            }

            if ($item['quantity'] > $product->quantity) {
                return ['message' => "Product quantity ({$product->name}) for invoice is not enough. You only have {$product->quantity} left", 'type' => 'error'];
            }

            $items[$key] = $item;
        }

        return ['type' => 'success', 'data' => $items];
    }

    /**
     * Generates a unique invoice number.
     *
     * Constructs a new invoice number based on the current count of invoices
     * and a specified increment value. Ensures that the generated invoice
     * number does not already exist in the database.
     *
     * @param int $int The increment value used to generate the invoice number (defaults to 1).
     * @return string The unique invoice number.
     */
    public function generateInvoiceNumber($int = 1): string
    {
        $batch = 'INV' . sprintf('%06d', Invoice::query()->count() + $int);

        $exist = Invoice::query()->where('invoice_number', $batch)->first();

        if ($exist === null) {

            return $batch;
        }

        return $this->generateInvoiceNumber(++$int);
    }

    public function processInvoiceData(Invoice $invoice, $items): float|int
    {
        $invoice_item_collection = collect();

        $item_total = 0;
        foreach ($items as $item) {

            $product = Product::query()->where('id', $item['product_id'])->first();

            $remaining_product = $product->quantity - $item['quantity'];

            $product->update(['quantity' => max($remaining_product, 0)]);

            $item_total += $item['unit_price'] * $item['quantity'];

            $invoice_item_collection->push([
                'invoice_id' => $invoice->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'amount' => $item_total,
                'created_at' => now(),
                'description' => $item['description'] ?? null,
            ]);

        }

        DB::table('invoice_items')->insert($invoice_item_collection->toArray());

        return $item_total;

    }

    /**
     * Reverses the associated invoice items data for a given invoice.
     *
     * Updates the product quantities by adding back the quantities from invoice items.
     * Deletes all invoice items associated with the specified invoice from the database.
     *
     * @param Invoice $invoice The invoice object containing the items to reverse.
     */
    public function reverseInvoiceItemsData(Invoice $invoice): void
    {

        if (!empty($invoice->invoiceItems)) {

            foreach ($invoice->invoiceItems as $item) {

                $product = Product::query()->where('id', $item->product_id)->first();

                $product->update(['quantity' => $product->quantity + $item->quantity]);

            }

        }

        DB::table('invoice_items')->where('invoice_id', $invoice->id)->delete();

    }

    /**
     * Converts an array of enum cases into an array of their corresponding values.
     *
     * Iterates over the provided enum cases, extracts their values, and returns them in a sequential array.
     *
     * @return array The array of enum values extracted from the provided cases.
     */
    public function convertEnumToArray($cases): array
    {
        $hook_status = array_values($cases);

        $status = [];

        foreach ($hook_status as $value) {
            $status[] = $value->value;
        }

        return $status;
    }
}
