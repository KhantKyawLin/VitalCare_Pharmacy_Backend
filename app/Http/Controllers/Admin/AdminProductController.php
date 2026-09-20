<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Picture;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class AdminProductController extends Controller
{
    #[OA\Get(
        path: '/api/admin/products',
        summary: 'List products with inventory metrics (Admin/Staff)',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'paracetamol')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated product list with dashboard metric badges'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (Missing permission)')
        ]
    )]
    public function index(Request $request)
    {
        $query = Product::with([
            'category', 
            'unit', 
            'pictures', 
            'movements' => function($q) {
                $q->with('purchaseProduct.purchase.supplier')->orderBy('id', 'desc');
            }, 
            'promotions'
        ]);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Execute pagination
        $products = $query->latest()->paginate($request->get('per_page', 15));

        // Compute global metrics
        $totalProducts = Product::count();
        $lowStockQuery = Product::withSum('movements as total_stock', 'instock_quantity')
            ->get()
            ->filter(function($p) {
                return ($p->total_stock ?? 0) <= $p->minimum_quantity;
            })->count();
        $expiringSoon = \App\Models\ProductMovement::where('instock_quantity', '>', 0)
            ->whereIn('movement_type', ['current', 'stored'])
            ->whereBetween('expired_date', [now(), now()->addDays(30)])
            ->count();
        $activeSuppliers = \App\Models\Supplier::count();

        return response()->json([
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
            'metrics' => [
                'total_products' => $totalProducts,
                'low_stock' => $lowStockQuery,
                'expiring_soon' => $expiringSoon,
                'active_suppliers' => $activeSuppliers,
            ]
        ]);
    }

    #[OA\Post(
        path: '/api/admin/products',
        summary: 'Create a new product with optional images',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['name', 'category_id', 'unit_id'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Amoxicillin 500mg'),
                        new OA\Property(property: 'category_id', type: 'integer', example: 1),
                        new OA\Property(property: 'unit_id', type: 'integer', example: 1),
                        new OA\Property(property: 'price', type: 'number', format: 'float', example: 12.50),
                        new OA\Property(property: 'minimum_quantity', type: 'integer', example: 10),
                        new OA\Property(property: 'description', type: 'string', example: 'Broad spectrum antibiotic'),
                        new OA\Property(property: 'usage', type: 'string', example: 'Take after meals'),
                        new OA\Property(property: 'side_effects', type: 'string', example: 'Mild nausea'),
                        new OA\Property(property: 'dosage', type: 'string', example: '1 capsule 3 times a day'),
                        new OA\Property(property: 'is_published', type: 'boolean', example: true),
                        new OA\Property(property: 'requires_prescription', type: 'boolean', example: true),
                        new OA\Property(property: 'is_chronic', type: 'boolean', example: false),
                        new OA\Property(property: 'refill_interval_days', type: 'integer', example: 30),
                        new OA\Property(property: 'images[]', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Product created successfully'),
            new OA\Response(response: 422, description: 'Validation error (e.g. name already present)'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function store(Request $request)
    {
        $name = trim($request->name ?? '');
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:100',
                'min:3',
                Rule::unique('products', 'name')->whereNull('deleted_at')
            ],
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'description' => 'nullable|string|max:500',
            'usage' => 'nullable|string|max:500',
            'side_effects' => 'nullable|string|max:500',
            'dosage' => 'nullable|string|max:100',
            'price' => 'nullable|numeric|min:0',
            'minimum_quantity' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
            'requires_prescription' => 'nullable|boolean',
            'is_chronic' => 'nullable|boolean',
            'refill_interval_days' => 'nullable|integer|min:1',

            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,gif|max:2048',
        ], [
            'name.unique' => "The product name '{$name}' is already present.",
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            $product = Product::create($request->only([
                'name', 'category_id', 'unit_id', 'description',
                'usage', 'side_effects', 'dosage', 'price', 'minimum_quantity', 'is_published', 'requires_prescription',
                'is_chronic', 'refill_interval_days'
            ]));

            // Handle image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');
                    Picture::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            DB::commit();

            ActivityLog::log('created', 'Product', $product->id, "Product '{$product->name}' created");

            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product->load(['category', 'unit', 'pictures'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create product: ' . $e->getMessage()], 500);
        }
    }

    #[OA\Post(
        path: '/api/admin/products/bulk',
        summary: 'Bulk create multiple products with duplicate-handling',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['products'],
                properties: [
                    new OA\Property(
                        property: 'products',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            required: ['name', 'category_id', 'unit_id'],
                            properties: [
                                new OA\Property(property: 'name', type: 'string', example: 'Product 1'),
                                new OA\Property(property: 'category_id', type: 'integer', example: 1),
                                new OA\Property(property: 'unit_id', type: 'integer', example: 1),
                                new OA\Property(property: 'price', type: 'number', example: 5.00),
                                new OA\Property(property: 'description', type: 'string', example: 'Description here'),
                                new OA\Property(property: 'is_published', type: 'boolean', example: true),
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'All products created'),
            new OA\Response(response: 200, description: 'Partial success (some items already present)'),
            new OA\Response(response: 422, description: 'Validation failed or all products already present')
        ]
    )]
    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string|max:100|min:3',
            'products.*.category_id' => 'required|exists:categories,id',
            'products.*.unit_id' => 'required|exists:units,id',
            'products.*.description' => 'nullable|string|max:500',
            'products.*.price' => 'nullable|numeric|min:0',
            'products.*.is_published' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $results = [];
        $created = [];
        $failedNames = [];
        $seenInBatch = [];

        DB::beginTransaction();
        try {
            foreach ($request->products as $index => $productData) {
                $name = trim($productData['name'] ?? '');
                $lower = strtolower($name);

                if (in_array($lower, $seenInBatch) || Product::whereRaw('LOWER(name) = ?', [$lower])->whereNull('deleted_at')->exists()) {
                    if (!in_array($name, $failedNames)) {
                        $failedNames[] = $name;
                    }
                    $results[] = ['index' => $index, 'name' => $name, 'status' => 'skipped', 'reason' => 'already present'];
                    continue;
                }

                $seenInBatch[] = $lower;
                $product = Product::create($productData);
                $created[] = $product;
                $results[] = ['index' => $index, 'id' => $product->id, 'name' => $product->name, 'status' => 'created'];
            }
            DB::commit();

            $successCount = count($created);
            $failedCount = count($failedNames);
            $namesFormatted = implode(', ', $failedNames);
            $verb = $failedCount > 1 ? 'are' : 'is';

            if ($successCount > 0 && $failedCount > 0) {
                $msg = "{$successCount} succeeded and {$failedCount} failed. {$namesFormatted} {$verb} already present.";
                return response()->json([
                    'status' => 'partial',
                    'message' => $msg,
                    'results' => $results
                ], 200);
            }

            if ($successCount === 0 && $failedCount > 0) {
                $msg = "{$failedCount} failed. {$namesFormatted} {$verb} already present.";
                return response()->json([
                    'status' => 'failed',
                    'message' => $msg,
                    'results' => $results
                ], 422);
            }

            ActivityLog::log('bulk_created', 'Product', null, count($results) . ' products bulk created');

            return response()->json(['status' => 'success', 'message' => 'Bulk create successful', 'results' => $results], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Bulk create failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update a product.
     */
    #[OA\Put(
        path: '/api/admin/products/{id}',
        summary: 'Update product details and sync active batches',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Amoxicillin 500mg'),
                        new OA\Property(property: 'category_id', type: 'integer', example: 1),
                        new OA\Property(property: 'unit_id', type: 'integer', example: 1),
                        new OA\Property(property: 'price', type: 'number', example: 14.00),
                        new OA\Property(property: 'is_published', type: 'boolean', example: true),
                        new OA\Property(property: 'requires_prescription', type: 'boolean', example: true),
                        new OA\Property(property: 'deleted_image_ids[]', type: 'array', items: new OA\Items(type: 'integer', example: 5)),
                        new OA\Property(property: 'images[]', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Product updated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Product not found')
        ]
    )]
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $old = $product->toArray();

        $name = trim($request->name ?? '');
        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'string',
                'max:100',
                'min:3',
                Rule::unique('products', 'name')->ignore($id)->whereNull('deleted_at')
            ],
            'category_id' => 'sometimes|exists:categories,id',
            'unit_id' => 'sometimes|exists:units,id',
            'price' => 'nullable|numeric|min:0',
            'is_published' => 'nullable|boolean',
            'requires_prescription' => 'nullable|boolean',
            'is_chronic' => 'nullable|boolean',
            'refill_interval_days' => 'nullable|integer|min:1',

            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,gif|max:2048',
        ], [
            'name.unique' => "The product name '{$name}' is already present.",
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $product->update($request->only([
            'name', 'category_id', 'unit_id', 'description',
            'usage', 'side_effects', 'dosage', 'price', 'minimum_quantity', 'is_published', 'requires_prescription',
            'is_chronic', 'refill_interval_days'
        ]));

        // If price was updated, sync it to all active/in-stock batches
        if ($request->filled('price')) {
            \App\Models\ProductMovement::where('product_id', $product->id)
                ->whereIn('movement_type', ['current', 'stored'])
                ->update(['sale_price' => $request->price]);
        }

        // Handle deleted images
        if ($request->has('deleted_image_ids')) {
            foreach ($request->deleted_image_ids as $picId) {
                $pic = Picture::where('product_id', $product->id)->find($picId);
                if ($pic) {
                    Storage::disk('public')->delete($pic->image_path);
                    $pic->delete();
                }
            }
        }

        // Handle new image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                Picture::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => false,
                ]);
            }
        }

        // Determine if price was specifically changed for enhanced logging
        $priceChanged = $request->filled('price') && (float)$old['price'] !== (float)$request->price;
        $action = $priceChanged ? 'price_updated' : 'updated';
        $desc = $priceChanged 
            ? "Product '{$product->name}' PRICE CHANGED from {$old['price']} to {$request->price}" 
            : "Product '{$product->name}' updated";

        ActivityLog::log($action, 'Product', $product->id, $desc, $old, $product->toArray());

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->load(['category', 'unit', 'pictures'])
        ]);
    }

    #[OA\Get(
        path: '/api/admin/products/{id}',
        summary: 'Get single product details with inventory history and active promotions',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product details'),
            new OA\Response(response: 404, description: 'Product not found')
        ]
    )]
    public function show($id)
    {
        $product = Product::with(['category', 'unit', 'pictures', 'movements', 'promotions'])->findOrFail($id);
        return response()->json($product);
    }

    #[OA\Delete(
        path: '/api/admin/products/{id}',
        summary: 'Soft-delete a product',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product deleted successfully'),
            new OA\Response(response: 404, description: 'Product not found')
        ]
    )]
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        ActivityLog::log('deleted', 'Product', $id, "Product '{$product->name}' deleted");
        return response()->json(['message' => 'Product deleted successfully']);
    }

    #[OA\Get(
        path: '/api/admin/products-search',
        summary: 'Search products for dropdown select options',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'para'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of matching products (max 10)')
        ]
    )]
    public function search(Request $request)
    {
        $query = Product::select('id', 'name', 'price', 'category_id', 'unit_id')
            ->with(['category', 'unit', 'pictures']);

        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        return response()->json($query->limit(10)->get());
    }

    #[OA\Patch(
        path: '/api/admin/products/{id}/toggle-publish',
        summary: 'Toggle public store visibility for a product',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Publication status updated'),
            new OA\Response(response: 404, description: 'Product not found')
        ]
    )]
    public function togglePublish($id)
    {
        $product = Product::findOrFail($id);
        $product->is_published = !$product->is_published;
        $product->save();

        $status = $product->is_published ? 'published' : 'unpublished';
        ActivityLog::log('updated', 'Product', $product->id, "Product '{$product->name}' has been {$status}");

        return response()->json([
            'message' => "Product " . ($product->is_published ? "published" : "unpublished") . " successfully",
            'is_published' => $product->is_published
        ]);
    }
}
