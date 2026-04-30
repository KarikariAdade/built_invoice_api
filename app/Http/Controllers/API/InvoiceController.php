<?php

namespace App\Http\Controllers\API;

use App\Constants\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\AppServices;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller
{
    private AppServices $appServices;

    public function __construct(AppServices $appServices) {
        $this->appServices = $appServices;
    }

    public function index(Request $request)
    {
        $data = $request->only(['status', 'from', 'to']);

        $status_codes = $this->appServices->convertEnumToArray(InvoiceStatus::cases());

        $validator = Validator::make($data, [
            'status' => 'nullable|in:'.implode(',', $status_codes),
            'from' => 'nullable|date',
            'to' => 'nullable|date|after:from'
        ]);

        if ($validator->fails()) {
            return $this->appServices->generateResponse($validator->errors()->first(), [], 400, 'error');
        }

        $dates = [
            'start_date' => !empty($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->copy()->startOfYear()->startOfDay(),
            'end_date' => !empty($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay()
        ];

        $invoices = Invoice::query()
            ->when(array_key_exists('status', $data) && $data['status'] !== null, function ($query) use ($data) {
                return $query->where('status', InvoiceStatus::from($data['status']));
            })
            ->when($dates['start_date'], function ($query) use ($data, $dates) {
                return $query->where('created_at', '>=', $dates['start_date']);
            })
            ->when($dates['end_date'], function ($query) use ($data, $dates) {
                return $query->where('created_at', '<=', $dates['end_date']);
            })
            ->with('customer')->where('created_by', auth()->guard('api')->user()->id)
            ->paginate(15);

        if ($invoices->isEmpty()) {
            return $this->appServices->generateResponse('Invoices not found', [], 404, 'error');
        }

        return $this->appServices->generateResponse('Invoices retrieved successfully', $invoices);
    }

    public function store(Request $request)
    {
        $data = $request->only(['customer_id', 'issue_date', 'due_date', 'items', 'description']);

        $validate = Validator::make($data, $this->validateData());

        if ($validate->fails()) {
            return $this->appServices->generateResponse($validate->errors()->first(), [], 400, 'error');
        }

        $customer = Customer::query()->where('id', $data['customer_id'])
            ->where('created_by', auth()->guard('api')->user()->id)->exists();

        if (!$customer) {
            return $this->appServices->generateResponse('Customer not found', [], 404, 'error');
        }

        DB::beginTransaction();

        try {

            $invoice_items = $this->appServices->runInvoiceItemProductChecks($data['items']);

            if ($invoice_items['type'] === 'error') {
                return $this->appServices->generateResponse($invoice_items['message'], [], 400, 'error');
            }

            $invoice_items = $invoice_items['data'];

            $invoice = Invoice::query()->create($this->dumpInvoice($data));

            $total_amount = $this->appServices->processInvoiceData($invoice, $invoice_items);

            $invoice->update(['total_amount' => $total_amount]);

            DB::commit();

            return $this->appServices->generateResponse('Invoice created successfully', $invoice);

        } catch (\Exception $exception) {
            DB::rollBack();

            $this->appServices->generateLog('invoice', ':: INVOICE CREATION ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('An error occurred while trying to create an invoice. Kindly try again', [], 500, 'error');
        }


    }

    public function details($invoice_number)
    {
        $invoice = Invoice::query()->with(['customer', 'invoiceItems'])
            ->where('invoice_number', $invoice_number)
            ->where('created_by', auth()->guard('api')->user()->id)
            ->first();

        if ($invoice === null) {
            return $this->appServices->generateResponse('Invoice not found', [], 404, 'error');
        }

        return $this->appServices->generateResponse('Invoice retrieved successfully', $invoice);
    }


    public function changeStatus(Request $request, $invoice_number)
    {

        $data = $request->only(['status']);

        $status_codes = $this->appServices->convertEnumToArray(InvoiceStatus::cases());

        $validate = Validator::make($data, ['status' => 'required|in:'.implode(',', $status_codes)]);

        if ($validate->fails()) {
            return $this->appServices->generateResponse($validate->errors()->first(), [], 400, 'error');
        }

        $invoice = Invoice::query()->where('invoice_number', $invoice_number)
            ->where('created_by', auth()->guard('api')->user()->id)->first();

        if ($invoice === null) {
            return $this->appServices->generateResponse('Invoice not found', [], 404, 'error');
        }

        DB::beginTransaction();

        try {

            $invoice->update(['status' => InvoiceStatus::from($data['status'])]);

            DB::commit();

            return $this->appServices->generateResponse('Invoice status updated successfully', $invoice);

        } catch (\Exception $exception) {
            DB::rollBack();

            $this->appServices->generateLog('invoice', ':: INVOICE STATUS UPDATE ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('An error occurred while trying to update an invoice status. Kindly try again', [], 500, 'error');

        }

    }


    public function delete($invoice_number)
    {
        try {

            $invoice = Invoice::query()->where('invoice_number', $invoice_number)
                ->where('created_by', auth()->guard('api')->user()->id)->first();

            if ($invoice === null) {
                return $this->appServices->generateResponse('Invoice not found', [], 404, 'error');
            }

            if ($invoice->status !== InvoiceStatus::DRAFT) {
                return $this->appServices->generateResponse('Invoice cannot be deleted. Only draft can be deleted', [], 400, 'error');
            }

            DB::table('invoice_items')->where('invoice_id', $invoice->id)->delete();

            $invoice->delete();

            DB::commit();

            return $this->appServices->generateResponse('Invoice deleted successfully', []);

        } catch (\Exception $exception) {
            $this->appServices->generateLog('invoice', ':: INVOICE DELETION ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('An error occurred while trying to delete an invoice. Kindly try again', [], 500, 'error');
        }
    }
    private function validateData()
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after:issue_date',
            'description' => 'nullable|string',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric',
            'items.*.description' => 'nullable|string'
        ];
    }

    private function dumpInvoice($data)
    {
        return [
            'customer_id' => $data['customer_id'],
            'invoice_number' => $this->appServices->generateInvoiceNumber(),
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'],
            'description' => $data['description'] ?? null,
            'created_by' => auth()->guard('api')->user()->id,
        ];
    }
}
