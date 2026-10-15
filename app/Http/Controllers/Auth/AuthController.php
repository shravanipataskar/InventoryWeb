<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ActivityLogger;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
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
        try {
            if (Auth::check()) {
                return redirect()->route('dashboard');
            }

            return view('auth.index');
        } catch (\Throwable $e) {
            Log::error('Failed to load landing page.', [
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to load the page. Please try again.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function showLogin()
    {
        try {
            return view('auth.login');
        } catch (\Throwable $e) {
            Log::error('Failed to load login page.', [
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to load the login page. Please try again.'
            );
        }
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

        try {
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

            if (Schema::hasColumn('users', 'is_active') && !$user->is_active) {
                return back()
                    ->withErrors([
                        'email' => 'This account is inactive. Contact your system administrator.',
                    ])
                    ->withInput($request->only('email', 'remember'));
            }

            $remember = $request->boolean('remember');

            Auth::login($user, $remember);

            $request->session()->regenerate();

            /*
            |--------------------------------------------------------------------------
            | Update Login Details and Activity Log
            |--------------------------------------------------------------------------
            */

            DB::beginTransaction();

            try {
                if (Schema::hasColumn('users', 'last_login_at')) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'last_login_at' => now(),
                            'updated_at' => now(),
                        ]);
                }

                ActivityLogger::log(
                    'Signed in',
                    'Account',
                    'User signed in.',
                    $user
                );

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();

                Log::error('Failed to record login activity.', [
                    'user_id' => $user->id,
                    'exception' => $e,
                ]);

                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withErrors([
                        'email' => 'Unable to complete sign in. Please try again.',
                    ])
                    ->withInput(
                        $request->only(
                            'email',
                            'remember'
                        )
                    );
            }

            return redirect()
                ->intended(
                    route('dashboard')
                )
                ->with(
                    'success',
                    'Welcome back! You are signed in.'
                );
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Login failed due to an unexpected exception.', [
                'email' => $request->input('email'),
                'exception' => $e,
            ]);

            if (Auth::check()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return back()
                ->withErrors([
                    'email' => 'Unable to complete sign in. Please try again.',
                ])
                ->withInput(
                    $request->only(
                        'email',
                        'remember'
                    )
                );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Forgot Password
    |--------------------------------------------------------------------------
    */

    public function showForgotPassword()
    {
        try {
            return view(
                'auth.forgot-password'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to load forgot password page.', [
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to load the page. Please try again.'
            );
        }
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'email',
            ],
        ]);

        try {
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
        } catch (\Throwable $e) {
            Log::error('Failed to send password reset link.', [
                'email' => $request->input('email'),
                'exception' => $e,
            ]);

            return back()
                ->withErrors([
                    'email' => 'Unable to process your request. Please try again later.',
                ])
                ->withInput();
        }
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
        try {
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
        } catch (\Throwable $e) {
            Log::error('Failed to load reset password page.', [
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to load the page. Please try again.'
            );
        }
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

        try {
            DB::beginTransaction();

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
                DB::commit();

                return redirect()
                    ->route('login')
                    ->with(
                        'status',
                        'Your password has been reset successfully. You can now sign in.'
                    );
            }

            DB::rollBack();

            return back()
                ->withErrors([
                    'email' => __($status),
                ])
                ->withInput(
                    $request->only('email')
                );
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Password reset failed.', [
                'email' => $request->input('email'),
                'exception' => $e,
            ]);

            return back()
                ->withErrors([
                    'email' => 'Unable to reset your password. Please try again.',
                ])
                ->withInput(
                    $request->only('email')
                );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    public function showProfile()
    {
        try {
            $user = Auth::user()->load('roles');

            return view(
                'profile.show',
                [
                    'user' => $user,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to load user profile.', [
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to load your profile. Please try again.'
            );
        }
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

        try {
            DB::beginTransaction();

            $user->name = $data['name'];

            $user->email = $data['email'];

            $user->save();

            DB::commit();

            return redirect()
                ->route('profile.show')
                ->with(
                    'success',
                    'Your profile has been updated successfully.'
                );
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Failed to update user profile.', [
                'user_id' => $user->id,
                'exception' => $e,
            ]);

            return back()
                ->with(
                    'error',
                    'Unable to update your profile. Please try again.'
                )
                ->withInput(
                    $request->only('name', 'email')
                );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Change Password Page
    |--------------------------------------------------------------------------
    */

    public function showChangePassword()
    {
        try {
            return view(
                'profile.change-password'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to load change password page.', [
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to load the page. Please try again.'
            );
        }
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

        try {
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

            DB::beginTransaction();

            $user->password = Hash::make(
                $data['password']
            );

            $user->remember_token = Str::random(
                60
            );

            $user->save();

            DB::commit();

            return redirect()
                ->route('profile.show')
                ->with(
                    'success',
                    'Your password has been changed successfully.'
                );
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Failed to update user password.', [
                'user_id' => $user->id,
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to change your password. Please try again.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(
        Request $request
    ) {
        try {
            try {
                ActivityLogger::log(
                    'Signed out',
                    'Account',
                    'User signed out.'
                );
            } catch (\Throwable $e) {
                Log::error('Failed to record logout activity.', [
                    'user_id' => Auth::id(),
                    'exception' => $e,
                ]);
            }

            Auth::logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            return redirect()
                ->route('landing')
                ->with(
                    'status',
                    'You have been signed out successfully.'
                );
        } catch (\Throwable $e) {
            Log::error('Logout failed.', [
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to complete logout. Please try again.'
            );
        }
    }
}