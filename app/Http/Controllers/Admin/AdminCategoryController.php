<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Unit;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class AdminCategoryController extends Controller
{
    #[OA\Get(
        path: '/api/admin/categories',
        summary: 'List all categories with statistics',
        tags: ['Categories'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of categories with products count and metrics',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'categories', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'stats', type: 'object')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden')
        ]
    )]
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

    #[OA\Post(
        path: '/api/admin/categories',
        summary: 'Create single category or bulk categories',
        description: 'Provide "name" for single creation, or "categories" array for bulk creation with duplicate control.',
        tags: ['Categories'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Pain & Fever Relief', description: 'Used for single creation'),
                    new OA\Property(
                        property: 'categories',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['Pain & Fever Relief', 'Eye Care', 'Digestive & Rehydration'],
                        description: 'Used for bulk creation'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Category or categories created successfully'),
            new OA\Response(response: 200, description: 'Partial creation (some succeeded, duplicates failed)'),
            new OA\Response(response: 422, description: 'Validation error or already present'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function store(Request $request)
    {
        // Bulk create: { categories: ['Name1', 'Name2', ...] }
        if ($request->has('categories') && is_array($request->categories)) {
            $validator = Validator::make($request->all(), [
                'categories' => 'required|array|min:1',
                'categories.*' => 'required|string|max:100',
            ]);
            if ($validator->fails()) return response()->json($validator->errors(), 422);

            $created = [];
            $failedNames = [];
            $seenInBatch = [];

            foreach ($request->categories as $rawName) {
                $name = trim($rawName);
                if (empty($name)) continue;

                $lower = strtolower($name);

                // Check duplicate in this batch or already in database
                if (in_array($lower, $seenInBatch) || Category::whereRaw('LOWER(name) = ?', [$lower])->exists()) {
                    if (!in_array($name, $failedNames)) {
                        $failedNames[] = $name;
                    }
                    continue;
                }

                $seenInBatch[] = $lower;
                $category = Category::create(['name' => $name]);
                ActivityLog::log('created', 'Category', $category->id, "Category '{$category->name}' created");
                $created[] = $category;
            }

            $successCount = count($created);
            $failedCount = count($failedNames);
            $namesFormatted = implode(', ', $failedNames);
            $verb = $failedCount > 1 ? 'are' : 'is';

            if ($successCount > 0 && $failedCount > 0) {
                $msg = "{$successCount} succeeded and {$failedCount} failed. {$namesFormatted} {$verb} already present.";
                return response()->json([
                    'status' => 'partial',
                    'message' => $msg,
                    'created_count' => $successCount,
                    'failed_count' => $failedCount,
                    'failed_names' => $failedNames,
                    'categories' => $created,
                ], 200);
            }

            if ($successCount === 0 && $failedCount > 0) {
                $msg = "{$failedCount} failed. {$namesFormatted} {$verb} already present.";
                return response()->json([
                    'status' => 'failed',
                    'message' => $msg,
                    'created_count' => 0,
                    'failed_count' => $failedCount,
                    'failed_names' => $failedNames,
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'message' => "{$successCount} categor" . ($successCount === 1 ? 'y' : 'ies') . " created successfully.",
                'categories' => $created,
            ], 201);
        }

        // Single create: { name: 'Name' }
        $name = trim($request->name);
        $validator = Validator::make(['name' => $name], [
            'name' => 'required|string|max:100|unique:categories,name',
        ], [
            'name.unique' => "The category name '{$name}' is already present.",
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "The category name '{$name}' is already present.",
                'errors' => $validator->errors()
            ], 422);
        }

        $category = Category::create(['name' => $name]);
        ActivityLog::log('created', 'Category', $category->id, "Category '{$category->name}' created");

        return response()->json([
            'status' => 'success',
            'message' => 'Category created successfully.',
            'category' => $category
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $name = trim($request->name);
        $validator = Validator::make(['name' => $name], [
            'name' => 'required|string|max:100|unique:categories,name,' . $id,
        ], [
            'name.unique' => "The category name '{$name}' is already present.",
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "The category name '{$name}' is already present.",
                'errors' => $validator->errors()
            ], 422);
        }

        $old = $category->name;
        $category->update(['name' => $name]);
        ActivityLog::log('updated', 'Category', $id, "Category renamed from '{$old}' to '{$name}'");

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
