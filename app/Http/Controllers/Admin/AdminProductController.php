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

class AdminProductController extends Controller
{
    /**
     * List all products with stock info.
     */
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
        $lowStockQuery = Product::with('movements')->get()->filter(function ($p) {
            $totalStock = $p->movements->sum('instock_quantity');
            return $totalStock <= $p->minimum_quantity;
        })->count();
        $expiringSoon = \App\Models\ProductMovement::where('instock_quantity', '>', 0)
            ->whereIn('movement_type', ['current', 'stored'])
            ->whereBetween('expired_date', [now(), now()->addDays(30)])
            ->count();
        $activeSuppliers = \App\Models\SupplyProduct::distinct('supplier_id')->count('supplier_id');

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

    /**
     * Create a single product.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|min:3',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'description' => 'nullable|string|max:500',
            'usage' => 'nullable|string|max:500',
            'side_effects' => 'nullable|string|max:500',
            'dosage' => 'nullable|string|max:100',
            'price' => 'nullable|numeric|min:0',
            'minimum_quantity' => 'nullable|integer|min:0',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            $product = Product::create($request->only([
                'name', 'category_id', 'unit_id', 'description',
                'usage', 'side_effects', 'dosage', 'price', 'minimum_quantity'
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

    /**
     * Bulk create products.
     */
    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string|max:100|min:3',
            'products.*.category_id' => 'required|exists:categories,id',
            'products.*.unit_id' => 'required|exists:units,id',
            'products.*.description' => 'nullable|string|max:500',
            'products.*.price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $results = [];
        DB::beginTransaction();
        try {
            foreach ($request->products as $index => $productData) {
                $product = Product::create($productData);
                $results[] = ['index' => $index, 'id' => $product->id, 'name' => $product->name, 'status' => 'created'];
            }
            DB::commit();

            ActivityLog::log('bulk_created', 'Product', null, count($results) . ' products bulk created');

            return response()->json(['message' => 'Bulk create successful', 'results' => $results], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Bulk create failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update a product.
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $old = $product->toArray();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100|min:3',
            'category_id' => 'sometimes|exists:categories,id',
            'unit_id' => 'sometimes|exists:units,id',
            'description' => 'nullable|string|max:500',
            'price' => 'nullable|numeric|min:0',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $product->update($request->only([
            'name', 'category_id', 'unit_id', 'description',
            'usage', 'side_effects', 'dosage', 'price', 'minimum_quantity'
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

        ActivityLog::log('updated', 'Product', $product->id, "Product '{$product->name}' updated", $old, $product->toArray());

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->load(['category', 'unit', 'pictures'])
        ]);
    }

    /**
     * Show a single product.
     */
    public function show($id)
    {
        $product = Product::with(['category', 'unit', 'pictures', 'movements', 'promotions'])->findOrFail($id);
        return response()->json($product);
    }

    /**
     * Delete a product (soft delete).
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        ActivityLog::log('deleted', 'Product', $id, "Product '{$product->name}' deleted");

    }
    /**
     * Search products for dropdowns 
     */
    public function search(Request $request)
    {
        $query = Product::select('id', 'name', 'price', 'category_id', 'unit_id')
            ->with(['category', 'unit', 'pictures']);

        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        return response()->json($query->limit(10)->get());
    }
}
