<?php

namespace App\Http\Controllers;

use App\Helpers\FileUploadHelper;
use App\Models\Category;
use App\Models\Location;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * GET /api/profile
     * Return the authenticated user with locations, store (+ category).
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $user->load(['locations', 'store.category']);

        return response()->json(['user' => $user]);
    }

    /**
     * POST /api/profile/info
     * Update firstname, lastname, contact_number, profile_picture.
     */
    public function updateInfo(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'firstname'       => 'required|string|max:255',
            'lastname'        => 'required|string|max:255',
            'contact_number'  => 'required|string|max:20',
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_picture'  => 'nullable|boolean',
        ]);

        $user->firstname      = $data['firstname'];
        $user->lastname       = $data['lastname'];
        $user->contact_number = $data['contact_number'];

        // Handle profile picture
        if ($request->hasFile('profile_picture')) {
            $user->profile_picture = FileUploadHelper::replace(
                'profile',
                $request->file('profile_picture'),
                $user->profile_picture
            );
        } elseif ($request->boolean('remove_picture')) {
            FileUploadHelper::delete($user->profile_picture);
            $user->profile_picture = null;
        }

        $user->save();
        $user->load(['locations', 'store.category']);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => $user,
        ]);
    }

    /**
     * POST /api/profile/address
     * Update or create the user's primary address.
     */
    public function updateAddress(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'region'   => 'required|string|max:255',
            'province' => 'nullable|string|max:255',
            'city'     => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
        ]);

        $type = $user->role === 'seller' ? 'store' : 'customer';

        Location::updateOrCreate(
            ['user_id' => $user->id, 'type' => $type],
            [
                'status'            => 1,
                'region'            => $data['region'],
                'province'          => $data['province'] ?? '',
                'city_municipality' => $data['city'],
                'barangay'          => $data['barangay'],
            ]
        );

        $user->load(['locations', 'store.category']);

        return response()->json([
            'message' => 'Address updated successfully.',
            'user'    => $user,
        ]);
    }

    /**
     * POST /api/profile/store
     * Update store details (seller only).
     */
    public function updateStore(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'seller') {
            return response()->json(['message' => 'Only sellers can update store details.'], 403);
        }

        $data = $request->validate([
            'store_name'        => 'required|string|max:255',
            'store_category'    => 'required|string|max:255',
            'store_description' => 'nullable|string|max:1000',
            'store_banner'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_banner'     => 'nullable|boolean',
        ]);

        $category = Category::firstOrCreate(
            ['name' => $data['store_category']],
            ['status' => 1]
        );

        $store = Store::firstOrNew(['user_id' => $user->id]);
        $store->store_name  = $data['store_name'];
        $store->category_id = $category->id;
        $store->description = $data['store_description'] ?? '';

        if ($request->hasFile('store_banner')) {
            $store->banner = FileUploadHelper::replace(
                'banners',
                $request->file('store_banner'),
                $store->banner
            );
        } elseif ($request->boolean('remove_banner')) {
            FileUploadHelper::delete($store->banner);
            $store->banner = null;
        }

        $store->save();
        $user->load(['locations', 'store.category']);

        return response()->json([
            'message' => 'Store updated successfully.',
            'user'    => $user,
        ]);
    }

    /**
     * POST /api/profile/password
     * Change user password.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors'  => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $user->password = bcrypt($data['password']);
        $user->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
