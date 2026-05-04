<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCustomerRequest;
use App\Http\Requests\Auth\RegisterSellerRequest;
use App\Helpers\FileUploadHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\TokenHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Location;
use App\Models\Store;
use App\Models\Category;
use App\Mail\VerifyEmailMail;
use App\Mail\ResetPasswordMail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    private function issueJwt(User $user): string
    {
        try {
            return JWTAuth::fromUser($user);
        } catch (JWTException $e) {
            throw new \RuntimeException('Could not create token', 500, $e);
        }
    }

    public function registerCustomer(RegisterCustomerRequest $request)
    {
        $data = $request->validated();

        $profilePicture = $request->hasFile('profile_picture')
            ? FileUploadHelper::storeImage('profile', $request->file('profile_picture'))
            : null;

        $user = User::create([
            'uuid'            => Str::uuid(),
            'firstname'       => $data['firstname'],
            'lastname'        => $data['lastname'],
            'contact_number'  => $data['contact_number'],
            'profile_picture' => $profilePicture,
            'role'            => 'customer',
            'email'           => $data['email'],
            'password'        => bcrypt($data['password']),
        ]);

        Location::create([
            'user_id'           => $user->id,
            'status'            => 1,
            'type'              => 'customer',
            'region'            => $data['region'],
            'province'          => $data['province'] ?? '',
            'city_municipality' => $data['city'],
            'barangay'          => $data['barangay'],
        ]);

        $jwtToken    = $this->issueJwt($user);
        $verifyToken = TokenHelper::create($user->id, 'email_verify', 5);

        Mail::to($user->email)->send(new VerifyEmailMail($user, $verifyToken));

        return response()->json([
            'user'    => $user,
            'token'   => $jwtToken,
            'message' => 'Account created! Please check your email to verify your account.',
        ], 201);
    }

    public function registerSeller(RegisterSellerRequest $request)
    {
        $data = $request->validated();

        $profilePicture = $request->hasFile('profile_picture')
            ? FileUploadHelper::storeImage('profile', $request->file('profile_picture'))
            : null;

        $storeBanner = $request->hasFile('store_banner')
            ? FileUploadHelper::storeImage('banners', $request->file('store_banner'))
            : null;

        $user = User::create([
            'uuid'            => Str::uuid(),
            'firstname'       => $data['firstname'],
            'lastname'        => $data['lastname'],
            'contact_number'  => $data['contact_number'],
            'profile_picture' => $profilePicture,
            'role'            => 'seller',
            'email'           => $data['email'],
            'password'        => bcrypt($data['password']),
        ]);

        Store::create([
            'user_id'     => $user->id,
            'store_name'  => $data['store_name'],
            'category_id' => $data['store_category'],
            'description' => $data['store_description'],
            'banner'      => $storeBanner,
        ]);

        Location::create([
            'user_id'           => $user->id,
            'status'            => 1,
            'type'              => 'store',
            'region'            => $data['region'],
            'province'          => $data['province'] ?? '',
            'city_municipality' => $data['city'],
            'barangay'          => $data['barangay'],
        ]);

        $jwtToken    = $this->issueJwt($user);
        $verifyToken = TokenHelper::create($user->id, 'email_verify', 5);

        Mail::to($user->email)->send(new VerifyEmailMail($user, $verifyToken));

        // Notify the new seller their account is pending review
        NotificationHelper::send(
            userId:  $user->id,
            type:    'store',
            title:   'Account Under Review',
            message: 'Your seller account has been submitted. Our team will verify your store details shortly.',
            data:    [
                'store_name' => $data['store_name'],
                'url' => '/seller/dashboard',
            ],
        );

        // Notify all admins that a new seller needs verification
        User::where('role', 'admin')->each(function (User $admin) use ($user, $data) {
            NotificationHelper::send(
                userId:  $admin->id,
                type:    'store',
                title:   'New Seller Registration',
                message: "{$user->firstname} {$user->lastname} registered as a seller and needs verification.",
                data:    [
                    'seller_id'  => $user->id,
                    'store_name' => $data['store_name'],
                    'url' => '/admin/seller-verify',
                ],
            );
        });

        return response()->json([
            'user'    => $user,
            'token'   => $jwtToken,
            'message' => 'Store account created! Please verify your email.',
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$jwtToken = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        $user = JWTAuth::user()->load('locations');

        // Block unverified accounts — invalidate the token immediately so it can't be reused
        if (is_null($user->email_verified_at)) {
            JWTAuth::setToken($jwtToken)->invalidate();
            return response()->json([
                'message'           => 'Please verify your email before logging in.',
                'email_unverified'  => true,
            ], 403);
        }

        return response()->json([
            'user'    => $user,
            'token'   => $jwtToken,
            'message' => 'Login successful.',
        ], 200);
    }

    public function verifyEmail(Request $request)
    {
        $tokenString = $request->query('token');

        if (!$tokenString) {
            return response()->json(['message' => 'Verification token is missing.'], 400);
        }

        $userToken = TokenHelper::find($tokenString, 'email_verify');

        if (!$userToken) {
            return response()->json(['message' => 'Invalid verification link.'], 400);
        }

        if (TokenHelper::isExpired($userToken)) {
            TokenHelper::consume($userToken);
            return response()->json(['message' => 'Verification link has expired.'], 400);
        }

        $user = $userToken->user;
        $user->email_verified_at = Carbon::now();
        $user->save();

        TokenHelper::consume($userToken);

        $jwtToken = $this->issueJwt($user);

        return response()->json([
            'user'    => $user,
            'token'   => $jwtToken,
            'message' => 'Email verified successfully.',
        ], 200);
    }

    public function resendVerification(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->email_verified_at) {
            // Generic message to avoid leaking whether the email exists
            return response()->json(['message' => 'If that email is registered and unverified, a new link has been sent.'], 200);
        }

        $verifyToken = TokenHelper::create($user->id, 'email_verify', 5);
        Mail::to($user->email)->send(new VerifyEmailMail($user, $verifyToken));

        return response()->json(['message' => 'Verification email sent. Please check your inbox.'], 200);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Generic message to prevent email enumeration
            return response()->json(['message' => 'If that email is registered, you will receive a reset link shortly.'], 200);
        }

        $resetToken = TokenHelper::create($user->id, 'password_reset', 5);
        Mail::to($user->email)->send(new ResetPasswordMail($user, $resetToken));

        return response()->json(['message' => 'If that email is registered, you will receive a reset link shortly.'], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $userToken = TokenHelper::find($request->token, 'password_reset');

        if (!$userToken) {
            return response()->json(['message' => 'Invalid or expired reset link.'], 400);
        }

        if (TokenHelper::isExpired($userToken)) {
            TokenHelper::consume($userToken);
            return response()->json(['message' => 'Reset link has expired. Please request a new one.'], 400);
        }

        $user = $userToken->user;
        $user->password = bcrypt($request->password);
        $user->save();

        TokenHelper::consume($userToken);

        return response()->json(['message' => 'Password reset successfully. You can now sign in.'], 200);
    }
}
