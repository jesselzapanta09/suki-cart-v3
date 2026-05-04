<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HomeProductSearchController extends Controller
{
    private const COMMON_PRODUCT_TERMS = [
        'and', 'the', 'with', 'for', 'from', 'your', 'this', 'that', 'these', 'those',
        'fresh', 'brand', 'new', 'pack', 'pcs', 'piece', 'pieces', 'size', 'small',
        'medium', 'large', 'premium', 'best', 'quality',
    ];

    /**
     * GET /api/products/search
     * Public product search endpoint - no authentication required
     * Filters: status='active', stock > 0
     * Supports search, pagination, and sorting
     */
    public function index(Request $request)
    {
        $query = $this->publicProductQuery();

        // Search by name or description
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Store filter
        if ($storeId = $request->input('store_id')) {
            $query->where('store_id', $storeId);
        }

        // Price range filter - now checking variant prices
        if ($minPrice = $request->input('min_price')) {
            $query->whereHas('variants', function ($q) use ($minPrice) {
                $q->where('price', '>=', (float) $minPrice);
            });
        }
        if ($maxPrice = $request->input('max_price')) {
            $query->whereHas('variants', function ($q) use ($maxPrice) {
                $q->where('price', '<=', (float) $maxPrice);
            });
        }

        // Sort
        $sortField = $request->input('sort_field', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        if ($sortField === 'latest') {
            $sortField = 'created_at';
        }

        $allowedSorts = ['id', 'name', 'created_at', 'price', 'sold'];
        $includesSold = false;
        if ($sortField === 'price') {
            $direction = $sortOrder === 'ascend' ? 'asc' : 'desc';

            $query->withMin([
                'variants as sort_price' => function ($variantQuery) {
                    $variantQuery->where('stock', '>', 0);
                },
            ], 'price')->orderBy('sort_price', $direction);
        } elseif ($sortField === 'sold') {
            $query->withSum([
                'orderItems as sold' => fn ($orderQuery) => $orderQuery->where('status', 'delivered'),
            ], 'quantity')->orderBy('sold', $sortOrder === 'ascend' ? 'asc' : 'desc');
            $includesSold = true;
        } elseif (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortOrder === 'ascend' ? 'asc' : 'desc');
        }

        $perPage = (int) $request->input('per_page', 10);
        $productsQuery = $query
            ->with(['images', 'category', 'store', 'variants'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        if (!$includesSold) {
            $productsQuery->withSum([
                'orderItems as sold' => fn ($orderQuery) => $orderQuery->where('status', 'delivered'),
            ], 'quantity');
        }

        $products = $productsQuery->paginate($perPage);

        $products->getCollection()->transform(function (Product $product) {
            return $this->decorateProductSummary($product);
        });

        return response()->json($products);
    }

    /**
     * GET /api/products/{uuid}/similar
     * Return similar public products for the given product.
     */
    public function similar(Request $request, string $uuid)
    {
        $product = $this->publicProductQuery()
            ->where('uuid', $uuid)
            ->with(['category', 'variants'])
            ->firstOrFail();

        $limit = max(1, min((int) $request->input('limit', 3), 12));
        $keywords = $this->extractKeywords([$product->name]);

        $candidatesQuery = $this->publicProductQuery()
            ->where('id', '!=', $product->id)
            ->with(['images', 'category', 'store', 'variants'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->withSum([
                'orderItems as sold' => fn ($orderQuery) => $orderQuery->where('status', 'delivered'),
            ], 'quantity');

        if ($product->category_id || !empty($keywords)) {
            $candidatesQuery->where(function ($query) use ($product, $keywords) {
                if ($product->category_id) {
                    $query->where('category_id', $product->category_id);
                }

                foreach ($keywords as $keyword) {
                    $query->orWhere('name', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                }
            });
        }

        $candidates = $candidatesQuery
            ->latest('created_at')
            ->limit(36)
            ->get()
            ->map(function (Product $candidate) use ($product, $keywords) {
                $candidate->similarity_score = $this->calculateSimilarityScore(
                    $product,
                    $candidate,
                    $keywords
                );

                return $candidate;
            })
            ->sort(function (Product $a, Product $b) {
                return [
                    $b->similarity_score,
                    (float) ($b->reviews_avg_rating ?? 0),
                    (int) ($b->sold ?? 0),
                    strtotime((string) $b->created_at),
                ] <=> [
                    $a->similarity_score,
                    (float) ($a->reviews_avg_rating ?? 0),
                    (int) ($a->sold ?? 0),
                    strtotime((string) $a->created_at),
                ];
            })
            ->take($limit)
            ->values()
            ->map(fn (Product $candidate) => $this->decorateProductSummary($candidate));

        return response()->json($candidates);
    }

    /**
     * GET /api/products/{uuid}
     * Get a single product detail with images, category, and store info
     * No authentication required (public)
     * Only accessible if the store is verified/approved
     */
    public function show(Request $request, $uuid)
    {
        $selectedRating = $request->input('review_rating', 'all');
        $reviewPage = max((int) $request->input('review_page', 1), 1);
        $reviewPerPage = max(1, min((int) $request->input('review_per_page', 5), 20));

        $product = $this->publicProductQuery()
            ->where('uuid', $uuid)
            ->with([
                'images',
                'category',
                'store',
                'variants',
                'reviews' => fn ($q) => $q->latest()->with(['user', 'variant']),
            ])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->withSum([
                'orderItems as sold' => fn ($orderQuery) => $orderQuery->where('status', 'delivered'),
            ], 'quantity')
            ->firstOrFail();

        return response()->json([
            'product' => $this->decorateProductDetail(
                $product,
                $selectedRating,
                $reviewPage,
                $reviewPerPage
            ),
        ]);
    }

    private function decorateProductSummary(Product $product): array
    {
        $payload = $product->toArray();
        $payload['rating'] = round((float) ($product->reviews_avg_rating ?? 0), 1);
        $payload['review_count'] = (int) ($product->reviews_count ?? 0);
        $payload['sold'] = (int) ($product->sold ?? 0);

        return $payload;
    }

    private function decorateProductDetail(
        Product $product,
        string|int $selectedRating = 'all',
        int $reviewPage = 1,
        int $reviewPerPage = 5
    ): array
    {
        $payload = $this->decorateProductSummary($product);
        $reviews = $product->reviews instanceof Collection ? $product->reviews : collect($product->reviews);
        $reviewCount = (int) ($product->reviews_count ?? $reviews->count());
        $averageRating = round((float) ($product->reviews_avg_rating ?? 0), 1);
        $storeSummary = $this->storeReviewSummary((int) $product->store_id);

        $distribution = collect(range(5, 1))->map(function (int $stars) use ($reviews, $reviewCount) {
            $count = $reviews->where('rating', $stars)->count();

            return [
                'stars' => $stars,
                'count' => $count,
                'percentage' => $reviewCount > 0 ? round(($count / $reviewCount) * 100) : 0,
            ];
        })->values()->all();

        $payload['review_summary'] = [
            'average_rating' => $averageRating,
            'review_count' => $reviewCount,
            'distribution' => $distribution,
        ];

        $selectedRating = $selectedRating === 'all' ? 'all' : (int) $selectedRating;
        $reviewQuery = ProductReview::query()
            ->where('product_id', $product->id)
            ->with(['user', 'variant'])
            ->latest();

        if ($selectedRating !== 'all' && $selectedRating >= 1 && $selectedRating <= 5) {
            $reviewQuery->where('rating', $selectedRating);
        } else {
            $selectedRating = 'all';
        }

        $paginatedReviews = $reviewQuery->paginate(
            $reviewPerPage,
            ['*'],
            'review_page',
            $reviewPage
        );

        $payload['reviews'] = collect($paginatedReviews->items())
            ->map(fn ($review) => $this->mapReview($review))
            ->values()
            ->all();
        $payload['review_filters'] = [
            'selected_rating' => $selectedRating,
        ];
        $payload['review_pagination'] = [
            'current_page' => $paginatedReviews->currentPage(),
            'per_page' => $paginatedReviews->perPage(),
            'total' => $paginatedReviews->total(),
            'last_page' => $paginatedReviews->lastPage(),
        ];

        if (!empty($payload['store'])) {
            $payload['store']['rating'] = $storeSummary['average_rating'];
            $payload['store']['review_count'] = $storeSummary['review_count'];
        }

        return $payload;
    }

    private function mapReview(ProductReview $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'review' => $review->review,
            'variant_name' => $review->variant_name,
            'created_at' => $review->created_at,
            'user' => $review->user ? [
                'id' => $review->user->id,
                'firstname' => $review->user->firstname,
                'lastname' => $review->user->lastname,
            ] : null,
        ];
    }

    private function storeReviewSummary(int $storeId): array
    {
        $summary = ProductReview::query()
            ->selectRaw('COUNT(*) as review_count, AVG(rating) as average_rating')
            ->whereHas('product', fn ($q) => $q->where('store_id', $storeId))
            ->first();

        return [
            'average_rating' => round((float) ($summary?->average_rating ?? 0), 1),
            'review_count' => (int) ($summary?->review_count ?? 0),
        ];
    }

    private function publicProductQuery()
    {
        return Product::query()
            ->where('status', 'active')
            ->whereHas('variants', function ($q) {
                $q->where('stock', '>', 0);
            })
            ->whereHas('store.verification', function ($q) {
                $q->where('store_status', 'approved');
            });
    }

    private function calculateSimilarityScore(
        Product $reference,
        Product $candidate,
        array $referenceKeywords
    ): float {
        $score = 0;

        if ($reference->category_id && $candidate->category_id === $reference->category_id) {
            $score += 60;
        }

        $candidateKeywords = $this->extractKeywords([$candidate->name]);

        $keywordOverlap = count(array_intersect($referenceKeywords, $candidateKeywords));
        $score += min($keywordOverlap * 10, 40);

        $score += min((float) ($candidate->reviews_avg_rating ?? 0), 5);
        $score += min(log10(((int) ($candidate->sold ?? 0)) + 1) * 3, 6);

        return round($score, 2);
    }

    private function extractKeywords(array $parts): array
    {
        return collect($parts)
            ->filter()
            ->flatMap(function ($part) {
                return preg_split('/[^a-z0-9]+/i', Str::lower((string) $part)) ?: [];
            })
            ->map(fn ($token) => trim($token))
            ->filter(function ($token) {
                return strlen($token) >= 3 && !in_array($token, self::COMMON_PRODUCT_TERMS, true);
            })
            ->unique()
            ->values()
            ->all();
    }

}
