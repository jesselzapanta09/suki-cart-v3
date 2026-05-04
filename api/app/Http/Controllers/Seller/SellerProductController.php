<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\FileUploadHelper;
use App\Http\Requests\Seller\StoreProductRequest;
use App\Http\Requests\Seller\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerProductController extends Controller
{
    private function ensureProductStatusMatchesStock(string $status, int $totalStock): void
    {
        if ($status === 'active' && $totalStock <= 0) {
            throw ValidationException::withMessages([
                'status' => ['Active products must have at least one variant with stock greater than 0.'],
            ]);
        }

        if ($status === 'out_of_stock' && $totalStock > 0) {
            throw ValidationException::withMessages([
                'status' => ['Out of Stock products cannot have variant stock greater than 0.'],
            ]);
        }
    }

    /**
     * GET /api/seller/products
     * Server-side paginated, sortable, searchable product list for authenticated seller.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Product::query()->where('store_id', $user->store->id);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sort
        $sortField = $request->input('sort_field', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['id', 'name', 'status', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder === 'ascend' ? 'asc' : 'desc');
        }

        $perPage = (int) $request->input('per_page', 10);
        $products = $query->with(['images', 'category', 'variants'])->paginate($perPage);

        return response()->json($products);
    }

    /**
     * GET /api/seller/products/{uuid}
     */
    public function show($uuid, Request $request)
    {
        $user = $request->user();
        $product = Product::query()
            ->where('store_id', $user->store->id)
            ->where('uuid', $uuid)
            ->with(['images', 'category', 'store', 'variants'])
            ->firstOrFail();
        return response()->json(['product' => $product]);
    }

    /**
     * POST /api/seller/products
     */
    public function store(StoreProductRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $user = $request->user();
            $data = $request->validated();
            $data['store_id'] = $user->store->id;
            $product = Product::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'weight' => $data['weight'] ?? null,
                'dimension' => $data['dimension'] ?? null,
                'specs' => !empty($data['specs']) ? $data['specs'] : null,
                'status' => $data['status'] ?? 'active',
                'store_id' => $data['store_id'],
            ]);
            foreach ($request->file('images', []) as $index => $image) {
                $path = FileUploadHelper::storeImage('products', $image);
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'sort_order' => $index,
                ]);
            }
            return response()->json([
                'message' => 'Product created successfully.',
                'product' => $product->load(['images', 'category', 'variants']),
            ], 201);
        });
    }

    /**
     * PUT /api/seller/products/{uuid}
     */
    public function update(UpdateProductRequest $request, $uuid)
    {
        return DB::transaction(function () use ($request, $uuid) {
            $user = $request->user();
            $product = Product::query()
                ->where('store_id', $user->store->id)
                ->where('uuid', $uuid)
                ->firstOrFail();
            $data = $request->validated();
            $nextStatus = $data['status'] ?? $product->status;
            $totalVariantStock = (int) $product->variants()->sum('stock');

            $this->ensureProductStatusMatchesStock($nextStatus, $totalVariantStock);

            $deletedImageIds = collect($data['deleted_image_ids'] ?? [])
                ->map(fn ($imageId) => (int) $imageId)
                ->unique()
                ->values();

            $product->update([
                'name' => $data['name'] ?? $product->name,
                'description' => $data['description'] ?? $product->description,
                'category_id' => $data['category_id'] ?? $product->category_id,
                'weight' => $data['weight'] ?? $product->weight,
                'dimension' => $data['dimension'] ?? $product->dimension,
                'specs' => isset($data['specs']) ? $data['specs'] : $product->specs,
                'status' => $data['status'] ?? $product->status,
            ]);

            if ($deletedImageIds->isNotEmpty()) {
                $imagesToDelete = $product->images()
                    ->whereIn('id', $deletedImageIds)
                    ->get();

                foreach ($imagesToDelete as $image) {
                    FileUploadHelper::delete($image->image_path);
                    $image->delete();
                }
            }

            $newImages = $request->file('images', []);
            $remainingImagesCount = $product->images()->count();
            $totalImages = $remainingImagesCount + count($newImages);

            if ($totalImages < 1) {
                throw ValidationException::withMessages([
                    'images' => ['At least one image is required'],
                ]);
            }

            if ($totalImages > 5) {
                throw ValidationException::withMessages([
                    'images' => ['A product can only have up to 5 images'],
                ]);
            }

            if (!empty($newImages)) {
                $nextSortOrder = ((int) $product->images()->max('sort_order')) + 1;

                foreach ($newImages as $image) {
                    $path = FileUploadHelper::storeImage('products', $image);
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'sort_order' => $nextSortOrder++,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Product updated successfully.',
                'product' => $product->load(['images', 'category', 'variants']),
            ]);
        });
    }

    /**
     * DELETE /api/seller/products/{uuid}
     */
    public function destroy(Request $request, $uuid)
    {
        $user = $request->user();
        $product = Product::query()
            ->where('store_id', $user->store->id)
            ->where('uuid', $uuid)
            ->with('images')
            ->firstOrFail();

        foreach ($product->images as $image) {
            FileUploadHelper::delete($image->image_path);
            $image->delete();
        }

        $product->delete();
        return response()->json(['message' => 'Product deleted successfully.']);
    }
}
