<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AppServices;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
class ProductController extends Controller
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

        $products = Product::query()
            ->where('created_by', auth()->guard('api')->user()->id)
            ->when($dates['start_date'], function ($query) use ($data, $dates) {
                return $query->where('created_at', '>=', $dates['start_date']);
            })
            ->when($dates['end_date'], function ($query) use ($data, $dates) {
                return $query->where('created_at', '<=', $dates['end_date']);
            })
            ->paginate(15);

        if ($products->isEmpty()) {
            return $this->appServices->generateResponse('Products not found', [], 404, 'error');
        }

        return $this->appServices->generateResponse('Products retrieved successfully', $products);
    }

    public function store(Request $request)
    {
        $data = $request->only(['name', 'unit_price', 'quantity', 'description']);

        $validator = Validator::make($data, $this->validateData());

        if ($validator->fails()) {
            return $this->appServices->generateResponse($validator->errors()->first(), [], 400, 'error');
        }

        DB::beginTransaction();

        try {

            $product = Product::query()->create($this->dumpData($data));

            DB::commit();

            return $this->appServices->generateResponse('Product created successfully', $product);

        } catch (\Exception $exception) {

            DB::rollBack();

            $this->appServices->generateLog('product', ':: PRODUCT CREATION ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('An error occurred while trying to create a product. Kindly try again', [], 500, 'error');
        }
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->only(['name', 'unit_price', 'quantity', 'description']);

        $validator = Validator::make($data, $this->validateData());

        if ($validator->fails()) {
            return $this->appServices->generateResponse($validator->errors()->first(), [], 400, 'error');
        }

        DB::beginTransaction();

        try {

            if ($product->created_by !== auth()->guard('api')->user()->id) {
                return $this->appServices->generateResponse('Product not found', [], 404, 'error');
            }

            $product->update($this->dumpData($data));

            DB::commit();

            return $this->appServices->generateResponse('Product updated successfully', $product);

        } catch (\Exception $exception) {

            DB::rollBack();

            $this->appServices->generateLog('product', ':: PRODUCT UPDATE ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('An error occurred while trying to update a product. Kindly try again', [], 500, 'error');
        }
    }

    public function details(Product $product)
    {
        if ($product->created_by !== auth()->guard('api')->user()->id) {
            return $this->appServices->generateResponse('Product not found', [], 404, 'error');
        }

        return $this->appServices->generateResponse('Product retrieved successfully', $product);
    }

    public function delete(Product $product)
    {
        DB::beginTransaction();

        try {

            $product->delete();

            DB::commit();

            return $this->appServices->generateResponse('Product deleted successfully', []);

        } catch (\Exception $exception) {

            DB::rollBack();

            $this->appServices->generateLog('product', ':: PRODUCT DELETE ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('An error occurred while trying to delete a product. Kindly try again', [], 500, 'error');

        }
    }

    private function validateData()
    {
        return [
            "name" => "required|string",
            "unit_price" => "required",
            "quantity" => "required",
            "description" => "nullable|string",
        ];
    }

    private function dumpData($data)
    {
        return [
            'name' => $data['name'],
            'unit_price' => $data['unit_price'],
            'quantity' => $data['quantity'],
            'description' => $data['description'] ?? null,
            'created_by' => auth()->guard('api')->user()->id,
        ];
    }
}
