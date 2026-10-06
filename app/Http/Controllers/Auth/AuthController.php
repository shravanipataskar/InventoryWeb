<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\LoginOtp;
use App\Mail\LoginOtpMail;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Constructor
     */
    public function __construct()
    {
        /*
        |--------------------------------------------------------------------------
        | Guest-only pages
        |--------------------------------------------------------------------------
        */

        $this->middleware('guest')->only([
            'showLogin',
            'login',
            'showRegister',
            'register',
            'showForgotPassword',
            'sendResetLink',
            'showResetPassword',
            'resetPassword',
        ]);

        /*
        |--------------------------------------------------------------------------
        | OTP pages
        |--------------------------------------------------------------------------
        */

        $this->middleware('guest')->only([
            'showVerifyOtp',
            'verifyOtp',
            'resendOtp',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Authenticated pages
        |--------------------------------------------------------------------------
        */

        $this->middleware('auth')->only([
            'showProfile',
            'updateProfile',
            'showChangePassword',
            'updatePassword',
            'logout',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Landing Page
    |--------------------------------------------------------------------------
    */

    public function showIndex()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.index');
    }


    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function showLogin()
    {
        return view('auth.login');
    }


    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
            ],
        ]);

        $user = User::where(
            'email',
            $credentials['email']
        )->first();

        /*
        |--------------------------------------------------------------------------
        | Check credentials
        |--------------------------------------------------------------------------
        */

        if (
            !$user ||
            !Hash::check(
                $credentials['password'],
                $user->password
            )
        ) {
            return back()
                ->withErrors([
                    'email' => 'The email or password you entered is incorrect.',
                ])
                ->withInput(
                    $request->only(
                        'email',
                        'remember'
                    )
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Generate OTP
        |--------------------------------------------------------------------------
        */

        $remember = $request->boolean('remember');

        $this->sendLoginOtp($user);

        /*
        |--------------------------------------------------------------------------
        | Store temporary authentication data
        |--------------------------------------------------------------------------
        |
        | User is NOT authenticated yet.
        | Authentication happens only after OTP verification.
        |
        */

        $request->session()->put([
            'otp_user_id' => $user->id,
            'otp_remember' => $remember,
            'otp_email' => $user->email,
        ]);

        return redirect()
            ->route('login.otp.show')
            ->with(
                'status',
                'A 6-digit verification code has been sent to your email.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Send Login OTP
    |--------------------------------------------------------------------------
    */

    protected function sendLoginOtp(User $user)
    {
        /*
        | Remove old unused OTPs
        */

        LoginOtp::where(
            'user_id',
            $user->id
        )
        ->whereNull('used_at')
        ->delete();

        /*
        | Generate 6-digit OTP
        */

        $otp = (string) random_int(
            100000,
            999999
        );

        /*
        | Save hashed OTP
        */

        LoginOtp::create([
            'user_id' => $user->id,

            'code_hash' => Hash::make($otp),

            'expires_at' => Carbon::now()->addMinutes(5),

            'attempts' => 0,
        ]);

        /*
        | Send OTP email
        */

        Mail::to($user->email)
            ->send(
                new LoginOtpMail(
                    $otp,
                    5
                )
            );
    }


    /*
    |--------------------------------------------------------------------------
    | OTP Verification Page
    |--------------------------------------------------------------------------
    */

    public function showVerifyOtp(Request $request)
    {
        if (
            !$request->session()->has(
                'otp_user_id'
            )
        ) {
            return redirect()->route('login');
        }

        return view('auth.verify-otp');
    }


    /*
    |--------------------------------------------------------------------------
    | Verify OTP
    |--------------------------------------------------------------------------
    */

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => [
                'required',
                'digits:6',
            ],
        ]);

        $userId = $request->session()->get(
            'otp_user_id'
        );

        if (!$userId) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'otp' => 'Your verification session has expired. Please login again.',
                ]);
        }

        $otpRecord = LoginOtp::where(
            'user_id',
            $userId
        )
        ->whereNull('used_at')
        ->latest('id')
        ->first();

        /*
        |--------------------------------------------------------------------------
        | OTP does not exist
        |--------------------------------------------------------------------------
        */

        if (!$otpRecord) {
            return back()->withErrors([
                'otp' => 'No active verification code was found. Please request a new code.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | OTP expired
        |--------------------------------------------------------------------------
        */

        if (
            $otpRecord->expires_at->isPast()
        ) {
            return back()->withErrors([
                'otp' => 'This verification code has expired. Please request a new code.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Too many attempts
        |--------------------------------------------------------------------------
        */

        if (
            $otpRecord->attempts >= 5
        ) {
            return back()->withErrors([
                'otp' => 'Too many incorrect attempts. Please request a new code.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Check OTP
        |--------------------------------------------------------------------------
        */

        if (
            !Hash::check(
                $request->otp,
                $otpRecord->code_hash
            )
        ) {
            $otpRecord->increment('attempts');

            return back()->withErrors([
                'otp' => 'The verification code is incorrect.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | OTP is valid
        |--------------------------------------------------------------------------
        */

        $user = User::findOrFail(
            $userId
        );

        $otpRecord->update([
            'used_at' => Carbon::now(),
        ]);

        /*
        | Get remember value
        */

        $remember = $request->session()->pull(
            'otp_remember',
            false
        );

        /*
        | Remove temporary OTP session
        */

        $request->session()->forget([
            'otp_user_id',
            'otp_email',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Login user
        |--------------------------------------------------------------------------
        */

        Auth::login(
            $user,
            $remember
        );

        $request->session()->regenerate();

        return redirect()
            ->intended(
                route('dashboard')
            )
            ->with(
                'success',
                'Welcome back! Your sign in has been verified.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Resend OTP
    |--------------------------------------------------------------------------
    */

    public function resendOtp(Request $request)
    {
        $userId = $request->session()->get(
            'otp_user_id'
        );

        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::findOrFail(
            $userId
        );

        $this->sendLoginOtp(
            $user
        );

        return back()->with(
            'status',
            'A new verification code has been sent. It is valid for 5 minutes.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    */

    public function showRegister()
    {
        return view('auth.register');
    }


    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create User
        |--------------------------------------------------------------------------
        */

        $user = User::create([
            'name' => $data['name'],

            'email' => $data['email'],

            'password' => Hash::make(
                $data['password']
            ),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Send OTP after registration
        |--------------------------------------------------------------------------
        */

        $this->sendLoginOtp(
            $user
        );

        /*
        |--------------------------------------------------------------------------
        | Store temporary OTP session
        |--------------------------------------------------------------------------
        */

        $request->session()->put([
            'otp_user_id' => $user->id,

            'otp_remember' => false,

            'otp_email' => $user->email,
        ]);

        /*
        |--------------------------------------------------------------------------
        | DO NOT LOGIN USER YET
        |--------------------------------------------------------------------------
        */

        return redirect()
            ->route('login.otp.show')
            ->with(
                'status',
                'Your account was created successfully. A 6-digit verification code has been sent to your email.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Forgot Password
    |--------------------------------------------------------------------------
    */

    public function showForgotPassword()
    {
        return view(
            'auth.forgot-password'
        );
    }


    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'email',
            ],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if (
            $status === Password::RESET_LINK_SENT
        ) {
            return back()->with(
                'status',
                'If an account exists for this email, a password reset link has been sent.'
            );
        }

        return back()
            ->withErrors([
                'email' => __($status),
            ])
            ->withInput();
    }


    /*
    |--------------------------------------------------------------------------
    | Reset Password
    |--------------------------------------------------------------------------
    */

    public function showResetPassword(
        Request $request,
        $token = null
    ) {
        return view(
            'auth.reset-password',
            [
                'token' => $token,

                'email' => $request->query(
                    'email',
                    ''
                ),
            ]
        );
    }


    public function resetPassword(
        Request $request
    ) {
        $data = $request->validate([
            'token' => [
                'required',
            ],

            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $status = Password::reset(
            $data,
            function (
                User $user,
                $password
            ) {

                $user->forceFill([
                    'password' => Hash::make(
                        $password
                    ),

                    'remember_token' => Str::random(
                        60
                    ),
                ])->save();
            }
        );

        if (
            $status === Password::PASSWORD_RESET
        ) {
            return redirect()
                ->route('login')
                ->with(
                    'status',
                    'Your password has been reset successfully. You can now sign in.'
                );
        }

        return back()
            ->withErrors([
                'email' => __($status),
            ])
            ->withInput(
                $request->only('email')
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    public function showProfile()
    {
        $user = Auth::user();

        return view(
            'profile.show',
            [
                'user' => $user,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Profile
    |--------------------------------------------------------------------------
    */

    public function updateProfile(
        Request $request
    ) {
        $user = Auth::user();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email,' . $user->id,
            ],
        ]);

        $user->name = $data['name'];

        $user->email = $data['email'];

        $user->save();

        return redirect()
            ->route('profile.show')
            ->with(
                'success',
                'Your profile has been updated successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Change Password Page
    |--------------------------------------------------------------------------
    */

    public function showChangePassword()
    {
        return view(
            'profile.change-password'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Password
    |--------------------------------------------------------------------------
    */

    public function updatePassword(
        Request $request
    ) {
        $data = $request->validate([
            'current_password' => [
                'required',
                'string',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | Check Current Password
        |--------------------------------------------------------------------------
        */

        if (
            !Hash::check(
                $data['current_password'],
                $user->password
            )
        ) {
            return back()
                ->withErrors([
                    'current_password' =>
                        'Your current password is incorrect.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Save New Password
        |--------------------------------------------------------------------------
        */

        $user->password = Hash::make(
            $data['password']
        );

        $user->remember_token = Str::random(
            60
        );

        $user->save();

        return redirect()
            ->route('profile.show')
            ->with(
                'success',
                'Your password has been changed successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(
        Request $request
    ) {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with(
                'status',
                'You have been signed out successfully.'
            );
    }
}