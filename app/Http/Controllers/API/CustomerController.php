<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AppServices;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    private AppServices $appServices;

    public function __construct(AppServices $appServices) {
        $this->appServices = $appServices;
    }

    public function index()
    {

    }
}
