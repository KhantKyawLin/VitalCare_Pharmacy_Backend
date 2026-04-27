<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Unit;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminCategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')->get();
        
        $totalCategories = $categories->count();
        $totalProducts = \App\Models\Product::count();
        $avgProducts = $totalCategories > 0 ? round($totalProducts / $totalCategories, 1) : 0;
        
        $topCategory = $categories->sortByDesc('products_count')->first();
        
        return response()->json([
            'categories' => $categories,
            'stats' => [
                'total_categories' => $totalCategories,
                'total_products' => $totalProducts,
                'avg_products' => $avgProducts,
                'top_category' => $topCategory ? [
                    'name' => $topCategory->name,
                    'count' => $topCategory->products_count
                ] : null
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:categories,name',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $category = Category::create(['name' => $request->name]);
        ActivityLog::log('created', 'Category', $category->id, "Category '{$category->name}' created");

        return response()->json(['message' => 'Category created', 'category' => $category], 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:categories,name,' . $id,
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $old = $category->name;
        $category->update(['name' => $request->name]);
        ActivityLog::log('updated', 'Category', $id, "Category renamed from '{$old}' to '{$request->name}'");

        return response()->json(['message' => 'Category updated', 'category' => $category]);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        ActivityLog::log('deleted', 'Category', $id, "Category '{$category->name}' deleted");

        return response()->json(['message' => 'Category deleted']);
    }
}
