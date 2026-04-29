<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AppServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private AppServices $appServices;

    public function __construct(AppServices $appServices) {
        $this->appServices = $appServices;
    }

    public function register(Request $request)
    {
        $data = $request->only(['name', 'email', 'password', 'password_confirmation']);

        $validate = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/|confirmed'
        ], ['password.regex' => 'Password should contain alphanumeric characters and at least one symbol']);

        if ($validate->fails()) {
            return $this->appServices->generateResponse($validate->errors()->first(), [], 400, 'error');
        }

        DB::beginTransaction();

        try {

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password'])
            ]);

            DB::commit();

            return $this->appServices->generateResponse('User created successfully', $user);

        } catch (\Exception $exception) {
            DB::rollBack();

            $this->appServices->generateLog('auth', ':: REGISTRATION ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('An error occurred while trying to register. Kindly try again', [], 500, 'error');

        }
    }


    public function login(Request $request)
    {
        $data = $request->only(['email', 'password']);

        $validate = Validator::make($data, [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string'
        ]);

        if ($validate->fails())
            return $this->appServices->generateResponse($validate->errors()->first(), [], 400, 'error');

        DB::beginTransaction();

        try {
            $credentials = ['email' => $data['email'], 'password' => $data['password']];

            $user = User::query()->where('email', $data['email'])->first();

            if ($token = Auth::guard('api')->attempt($credentials)) {

                $token = [
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => auth()->factory()->getTTL() * 60,
                    'user' => $user
                ];
                return $this->appServices->generateResponse('User successfully authenticated',  $token);

            }

            return $this->appServices->generateResponse('Invalid login credentials', [], 400, 'error');

        }catch (\Exception $exception){
            $this->appServices->generateLog('auth', ':: USER AUTHENTICATION ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('An error occurred while trying to login. Kindly try again', [], 500, 'error');
        }
    }
}
