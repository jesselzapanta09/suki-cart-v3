<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\FileUploadHelper;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Models\Store;
use App\Models\Location;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    /**
     * GET /api/admin/users
     * Server-side paginated, sortable, searchable user list.
     */
    public function index(Request $request)
    {
        $query = User::query()->where('id', '!=', 1);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('role', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // Email Verified filter
        if ($request->has('verified')) {
            $verified = $request->input('verified');
            if ($verified === '1' || $verified === 1) {
                $query->whereNotNull('email_verified_at');
            } elseif ($verified === '0' || $verified === 0) {
                $query->whereNull('email_verified_at');
            }
        }

        // Sort
        $sortField = $request->input('sort_field', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['id', 'firstname', 'lastname', 'email', 'role', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder === 'ascend' ? 'asc' : 'desc');
        }

        $perPage = (int) $request->input('per_page', 10);
        $users = $query->paginate($perPage);

        return response()->json($users);
    }

    /**
     * GET /api/admin/users/{id}
     */
    public function show($id)
    {
        $user = User::with(['locations', 'store.category'])->findOrFail($id);
        return response()->json(['user' => $user]);
    }

    /**
     * POST /api/admin/users
     */
    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $profilePicture = $request->hasFile('profile_picture')
            ? FileUploadHelper::storeImage('profile', $request->file('profile_picture'))
            : null;

        $user = User::create([
            'uuid'             => Str::uuid(),
            'firstname'        => $data['firstname'],
            'lastname'         => $data['lastname'],
            'email'            => $data['email'],
            'role'             => $data['role'],
            'contact_number'   => $data['contact_number'] ?? null,
            'password'         => bcrypt($data['password']),
            'profile_picture'  => $profilePicture,
            'email_verified_at'=> null,
        ]);

        // Create store if seller
        if ($data['role'] === 'seller' && !empty($data['store_name'])) {
            $storeBanner = $request->hasFile('store_banner')
                ? FileUploadHelper::storeImage('banners', $request->file('store_banner'))
                : null;

            $category = Category::firstOrCreate(
                ['name' => $data['store_category']],
                ['status' => 1]
            );

            Store::create([
                'user_id'     => $user->id,
                'store_name'  => $data['store_name'],
                'category_id' => $category->id,
                'description' => $data['store_description'] ?? null,
                'banner'      => $storeBanner,
            ]);
        }

        // Create location if address provided
        if (!empty($data['region'])) {
            Location::create([
                'user_id'           => $user->id,
                'status'            => 1,
                'type'              => $data['role'] === 'seller' ? 'store' : 'customer',
                'region'            => $data['region'],
                'province'          => $data['province'] ?? '',
                'city_municipality' => $data['city'],
                'barangay'          => $data['barangay'],
            ]);
        }

        return response()->json([
            'message' => 'User created successfully.',
            'user'    => $user->load(['locations', 'store.category']),
        ], 201);
    }

    /**
     * POST /api/admin/users/{id}
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validated();

        $user->firstname      = $data['firstname'];
        $user->lastname       = $data['lastname'];
        $user->email          = $data['email'];
        $user->role           = $data['role'];
        $user->contact_number = $data['contact_number'] ?? $user->contact_number;

        if (!empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }

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

        // Update or create store if seller
        if ($data['role'] === 'seller' && !empty($data['store_name'])) {
            $storeBanner = null;
            if ($request->hasFile('store_banner')) {
                $storeBanner = FileUploadHelper::storeImage('banners', $request->file('store_banner'));
            }

            $category = Category::firstOrCreate(
                ['name' => $data['store_category']],
                ['status' => 1]
            );

            $storeData = [
                'store_name'  => $data['store_name'],
                'category_id' => $category->id,
                'description' => $data['store_description'] ?? null,
            ];

            if ($storeBanner) {
                // Delete old banner if replacing
                if ($user->store?->banner) {
                    FileUploadHelper::delete($user->store->banner);
                }
                $storeData['banner'] = $storeBanner;
            }

            Store::updateOrCreate(
                ['user_id' => $user->id],
                $storeData
            );
        } elseif ($data['role'] !== 'seller' && $user->store) {
            // If role changed away from seller, remove the store
            if ($user->store->banner) {
                FileUploadHelper::delete($user->store->banner);
            }
            $user->store->delete();
        }

        // Update or create location if address provided
        if (!empty($data['region'])) {
            Location::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'status'            => 1,
                    'type'              => $data['role'] === 'seller' ? 'store' : 'customer',
                    'region'            => $data['region'],
                    'province'          => $data['province'] ?? '',
                    'city_municipality' => $data['city'],
                    'barangay'          => $data['barangay'],
                ]
            );
        }

        return response()->json([
            'message' => 'User updated successfully.',
            'user'    => $user->load(['locations', 'store.category']),
        ]);
    }

    /**
     * DELETE /api/admin/users/{id}
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === 1) {
            return response()->json(['message' => 'The primary admin account cannot be deleted.'], 403);
        }

        // Prevent self-delete
        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        FileUploadHelper::delete($user->profile_picture);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
