<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    /**
     * GET /api/admin/categories
     */
    public function index(Request $request)
    {
        $query = Category::query();

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Sort
        $sortField = $request->input('sort_field', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['id', 'name', 'status', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder === 'ascend' ? 'asc' : 'desc');
        }

        $perPage = (int) $request->input('per_page', 10);
        $categories = $query->withCount('stores')->paginate($perPage);

        return response()->json($categories);
    }

    /**
     * GET /api/admin/categories/{id}
     */
    public function show($id)
    {
        $category = Category::withCount('stores')->findOrFail($id);
        return response()->json(['category' => $category]);
    }

    /**
     * POST /api/admin/categories
     */
    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();

        $category = Category::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'status'      => $data['status'] ?? 1,
        ]);

        return response()->json([
            'message'  => 'Category created successfully.',
            'category' => $category,
        ], 201);
    }

    /**
     * PUT /api/admin/categories/{id}
     */
    public function update(UpdateCategoryRequest $request, $id)
    {
        $category = Category::findOrFail($id);
        $data = $request->validated();

        $category->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? $category->description,
            'status'      => $data['status'] ?? $category->status,
        ]);

        return response()->json([
            'message'  => 'Category updated successfully.',
            'category' => $category,
        ]);
    }

    /**
     * DELETE /api/admin/categories/{id}
     */
    public function destroy($id)
    {
        $category = Category::withCount('stores')->findOrFail($id);

        if ($category->stores_count > 0) {
            return response()->json([
                'message' => 'Cannot delete category with associated stores.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully.']);
    }
}
