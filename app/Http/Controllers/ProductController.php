<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Display a listing of products with their primary images.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $perPage = request()->input('per_page', 20); // Default to 20 items per page

        $products = Product::with(['images' => function($query) {
            $query->orderByRaw("CASE WHEN variant = 'original' THEN 0 ELSE 1 END");
        }])->paginate($perPage);

        // Transform the paginated collection
        $transformedProducts = $products->getCollection()->map(function($product) {
            // Get all variants for the primary image
            $primaryImage = $product->images->first();
            $imageVariants = [];

            if ($primaryImage) {
                $imageVariants = $product->images->mapWithKeys(function($image) use ($product) {
                    return [
                        $image->variant => [
                            'url' => asset('storage/' . $image->path),
                            'width' => $image->width,
                            'height' => $image->height,
                            'mime' => $image->mime
                        ]
                    ];
                })->toArray();
            }

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'created_at' => $product->created_at->format('Y-m-d H:i:s'),
                'primary_image' => $primaryImage ? [
                    'path' => asset($primaryImage->path),
                    'alt' => $product->name,
                    'variants' => $imageVariants
                ] : null,
                'images_count' => $product->images->count()
            ];
        });

        // Replace the collection with our transformed collection
        $products->setCollection($transformedProducts);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => [
                'per_page' => $perPage
            ]
        ]);
    }

    /**
     * Display the specified product with all its images.
     *
     * @param  string  $sku
     * @return \Inertia\Response
     */
    public function show($sku)
    {
        $product = Product::with('images')->where('sku', $sku)->firstOrFail();

        $formattedProduct = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'created_at' => $product->created_at->format('Y-m-d H:i:s'),
            'images' => $product->images->map(function($image) use ($product) {
                return [
                    'id' => $image->id,
                    'path' => asset($image->path),
                    'is_primary' => (bool)$image->pivot->is_primary,
                    'variant' => $image->variant,
                    'dimensions' => "{$image->width}x{$image->height}",
                    'mime' => $image->mime
                ];
            }),
            'primary_image' => $product->images->first(function($image) {
                return $image->pivot->is_primary;
            })
        ];

        return Inertia::render('Products/Show', [
            'product' => $formattedProduct
        ]);
    }
}
