<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminPromotionController extends Controller
{
    public function index()
    {
        $promotions = Promotion::withCount('products')->orderBy('created_at', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'promotions' => $promotions
        ]);
    }

    public function products()
    {
        // For the product selection table when creating item-scoped promotions
        $products = Product::with('category')->get()->map(function($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'category_name' => $product->category ? $product->category->name : 'N/A',
                'category_id' => $product->category_id,
                'price' => $product->price,
                // Include any active promotion info to prevent overlaps
                'active_promotions' => $product->promotions()->active()->get(['promotions.id', 'title'])
            ];
        });

        return response()->json([
            'status' => 'success',
            'products' => $products
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:100',
            'type' => 'required|in:percentage,fixed_amount,cashback,buy_one_get_one,buy_one_get_gift',
            'discount_value' => 'required|numeric|min:0',
            'promotion_scope' => 'required|in:item,order',
            'min_qty_requirement' => 'required|integer|min:1',
            'min_order_value' => 'required_if:promotion_scope,order|numeric|min:0',
            'max_usage_per_bill' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'product_ids' => 'required_if:promotion_scope,item|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $promotion = Promotion::create($request->only([
                'title', 'type', 'description', 'discount_value', 
                'promotion_scope', 'min_qty_requirement', 'min_order_value', 'max_usage_per_bill',
                'start_date', 'end_date', 'is_active'
            ]));

            if ($request->promotion_scope === 'item' && $request->has('product_ids')) {
                $promotion->products()->sync($request->product_ids);
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Promotion created successfully',
                'promotion' => $promotion->load('products')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create promotion: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $promotion = Promotion::with('products')->findOrFail($id);
        return response()->json([
            'status' => 'success',
            'promotion' => $promotion
        ]);
    }

    public function update(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:100',
            'type' => 'required|in:percentage,fixed_amount,cashback,buy_one_get_one,buy_one_get_gift',
            'discount_value' => 'required|numeric|min:0',
            'promotion_scope' => 'required|in:item,order',
            'min_qty_requirement' => 'required|integer|min:1',
            'min_order_value' => 'required_if:promotion_scope,order|numeric|min:0',
            'max_usage_per_bill' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'product_ids' => 'required_if:promotion_scope,item|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $promotion->update($request->only([
                'title', 'type', 'description', 'discount_value', 
                'promotion_scope', 'min_qty_requirement', 'min_order_value', 'max_usage_per_bill',
                'start_date', 'end_date', 'is_active'
            ]));

            if ($request->promotion_scope === 'item') {
                $promotion->products()->sync($request->input('product_ids', []));
            } else {
                // If scope changed to order, remove existing product associations
                $promotion->products()->detach();
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Promotion updated successfully',
                'promotion' => $promotion->load('products')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update promotion: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $promotion = Promotion::findOrFail($id);
            $promotion->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Promotion deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete promotion'
            ], 500);
        }
    }
}
