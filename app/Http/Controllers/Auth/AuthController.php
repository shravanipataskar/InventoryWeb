<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        $remember = $request->boolean('remember');

        Auth::login($user, $remember);

        $request->session()->regenerate();

        return redirect()
            ->intended(
                route('dashboard')
            )
            ->with(
                'success',
                'Welcome back! You are signed in.'
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

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with(
                'success',
                'Your account was created successfully and you are now signed in.'
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