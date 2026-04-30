<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AppServices;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Claims\Custom;

class CustomerController extends Controller
{
    private AppServices $appServices;

    public function __construct(AppServices $appServices) {
        $this->appServices = $appServices;
    }

    public function index()
    {
        $data = request()->only(['from', 'to']);

        $validator = Validator::make($data, [
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

        $customers = Customer::query()
            ->when($dates['start_date'], function ($query) use ($data, $dates) {
                return $query->where('created_at', '>=', $dates['start_date']);
            })
            ->when($dates['end_date'], function ($query) use ($data, $dates) {
                return $query->where('created_at', '<=', $dates['end_date']);
            })
            ->where('created_by', auth()->guard('api')->user()->id)
            ->paginate(15);

        return $this->appServices->generateResponse('Customers retrieved successfully', $customers);
    }

    public function store(Request $request)
    {
        $data = $request->only(['name', 'email', 'phone', 'address', 'description']);

        $validate = Validator::make($data, $this->validateCustomer());

        if ($validate->fails()) {
            return $this->appServices->generateResponse($validate->errors()->first(), [], 400, 'error');
        }

        DB::beginTransaction();

        try {

            $customer = Customer::query()->create($this->dumpCustomer($data));

            DB::commit();

            return $this->appServices->generateResponse('Customer created successfully', $customer);

        } catch (\Exception $exception) {
            DB::rollBack();

            $this->appServices->generateLog('customer', ':: CUSTOMER CREATION ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('An error occurred while trying to create a customer. Kindly try again', [], 500, 'error');

        }
    }

    public function details(Customer $customer)
    {
        if ($customer->created_by !== auth()->guard('api')->user()->id)
            return $this->appServices->generateResponse('Customer not found', [], 404, 'error');

        return $this->appServices->generateResponse('Customer retrieved successfully', $customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->only(['name', 'email', 'phone', 'address', 'description']);

        $validate = Validator::make($data, array_merge($this->validateCustomer($customer->id)));

        if ($validate->fails()) {
            return $this->appServices->generateResponse($validate->errors()->first(), [], 400, 'error');
        }

        DB::beginTransaction();

        try {

            if ($customer->created_by !== auth()->guard('api')->user()->id) {
                return $this->appServices->generateResponse('Customer not found', [], 404, 'error');
            }

            $customer->update($this->dumpCustomer($data));

            DB::commit();

            return $this->appServices->generateResponse('Customer updated successfully', $customer);

        } catch (\Exception $exception) {
            DB::rollBack();

            $this->appServices->generateLog('customer', ':: CUSTOMER UPDATE ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('An error occurred while trying to update a customer. Kindly try again', [], 500, 'error');


        }
    }

    public function delete(Customer $customer)
    {
        DB::beginTransaction();

        try {

            $customer->delete();

            DB::commit();

            return $this->appServices->generateResponse('Customer deleted successfully', []);

        } catch (\Exception $exception) {

            DB::rollBack();

            $this->appServices->generateLog('customer', ':: CUSTOMER DELETE ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('An error occurred while trying to delete a customer. Kindly try again', [], 500, 'error');

        }
    }

    private function validateCustomer($id = null): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email|unique:customers,email,'.$id,
            'phone' => 'required|string|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:18|unique:customers,phone,'.$id,
            'address' => 'nullable|string',
            'description' => 'nullable|string',
        ];
    }

    private function dumpCustomer($data): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
            'description' => $data['description'] ?? null,
            'created_by' => auth()->guard('api')->user()->id,
        ];
    }
}
