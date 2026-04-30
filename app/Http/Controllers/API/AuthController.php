<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AuthenticationOtpNotification;
use App\Services\AppServices;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function forgotPassword(Request $request)
    {
        $data = $request->only(['email']);

        $validator = Validator::make($data, ['email' => "required|exists:users,email"]);

        if ($validator->fails()) {
            return $this->appServices->generateResponse($validator->errors()->first(), [], 'error');
        }

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null) {
            return $this->appServices->generateResponse('Invalid account details', [], 'error');
        }

        DB::beginTransaction();

        try {
            $otp = random_int(111111, 999999);

            $data = [
                'otp' => $otp,
                'type' => 'reset',
                'message' => 'The number below is your password reset code. Do not share the code with anyone. Code expires in 30 minutes'
            ];

            $user->update(['otp' => $otp, 'otp_expiry' => now()->addMinutes(30)]);

            $user->notify((new AuthenticationOtpNotification($data))->afterCommit());

            DB::commit();

            return $this->appServices->generateResponse('OTP sent successfully. Kindly check your email', []);

        } catch (\Exception $exception) {

            DB::rollBack();

            $this->appServices->generateLog('auth', ':: USER FORGOT PASSWORD ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('An error occurred while trying to reset your password. Kindly try again', [], 'error');
        }

    }

    public function resetPassword(Request $request)
    {
        $data = $request->only(['email', 'otp', 'password', 'password_confirmation']);

        $validate = Validator::make($data, [
            'email' => 'required|exists:users,email',
            'password' => 'confirmed|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'
        ], ['password.regex' => 'Password should contain alphanumeric characters and at least one symbol']);

        if ($validate->fails())
            return $this->appServices->generateResponse($validate->errors()->first(), [], 'error');

        $otp = User::query()->where('email', $data['email'])->where('otp', $data['otp'])->first();

        DB::beginTransaction();

        try {

            if (!empty($otp)) {

                if (Carbon::now()->lte($otp->otp_expiry)) {

                    $otp->update(['otp_expiry' => null, 'otp' => null, 'password' => bcrypt($data['password'])]);

                    DB::commit();

                    return $this->appServices->generateResponse('Password reset successful.', ['email' => $otp->email]);

                }
                return $this->appServices->generateResponse('Password reset code expired.', ['email' => $otp->email], 'error');

            }

            return $this->appServices->generateResponse('Invalid User details', [], 'error');

        } catch (\Exception $exception) {

            DB::rollBack();

            $this->appServices->generateLog('auth', ':: USER PASSWORD RESET ERROR ::', $this->appServices->generateLogData($exception));

            return $this->appServices->generateResponse('Password reset failed. Kindly try again', '', 'error');

        }

    }

}
