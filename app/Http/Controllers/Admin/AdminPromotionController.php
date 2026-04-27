<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminPromotionController extends Controller
{
    public function index()
    {
        return response()->json(Promotion::with('products')->latest()->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:100',
            'type' => 'required|in:percentage,fixed_amount',
            'description' => 'nullable|string',
            'discount_value' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $promotion = Promotion::create($request->only([
            'title', 'type', 'description', 'discount_value', 'start_date', 'end_date'
        ]));

        if ($request->has('product_ids')) {
            $promotion->products()->sync($request->product_ids);
        }

        ActivityLog::log('created', 'Promotion', $promotion->id, "Promotion '{$promotion->title}' created");

        return response()->json(['message' => 'Promotion created', 'promotion' => $promotion->load('products')], 201);
    }

    public function update(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->update($request->only([
            'title', 'type', 'description', 'discount_value', 'start_date', 'end_date', 'is_active'
        ]));

        if ($request->has('product_ids')) {
            $promotion->products()->sync($request->product_ids);
        }

        ActivityLog::log('updated', 'Promotion', $id, "Promotion '{$promotion->title}' updated");

        return response()->json(['message' => 'Promotion updated', 'promotion' => $promotion->load('products')]);
    }

    public function destroy($id)
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->delete();
        ActivityLog::log('deleted', 'Promotion', $id, "Promotion '{$promotion->title}' deleted");

        return response()->json(['message' => 'Promotion deleted']);
    }
}
