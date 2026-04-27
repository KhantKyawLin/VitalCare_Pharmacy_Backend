<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminSupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::withCount(['products', 'purchases'])->get();
        
        $totalSuppliers = $suppliers->count();
        $totalOrders = $suppliers->sum('purchases_count');
        $avgProducts = $totalSuppliers > 0 ? round($suppliers->avg('products_count'), 1) : 0;

        return response()->json([
            'suppliers' => $suppliers,
            'stats' => [
                'total_suppliers' => $totalSuppliers,
                'avg_products' => $avgProducts,
                'total_orders' => $totalOrders
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $supplier = Supplier::create($request->only(['name', 'phone', 'address']));
        ActivityLog::log('created', 'Supplier', $supplier->id, "Supplier '{$supplier->name}' created");

        return response()->json(['message' => 'Supplier created', 'supplier' => $supplier], 201);
    }

    public function show($id)
    {
        return response()->json(Supplier::with(['products', 'purchases'])->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update($request->only(['name', 'phone', 'address']));
        ActivityLog::log('updated', 'Supplier', $id, "Supplier '{$supplier->name}' updated");

        return response()->json(['message' => 'Supplier updated', 'supplier' => $supplier]);
    }

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();
        ActivityLog::log('deleted', 'Supplier', $id, "Supplier '{$supplier->name}' deleted");

        return response()->json(['message' => 'Supplier deleted']);
    }
}
