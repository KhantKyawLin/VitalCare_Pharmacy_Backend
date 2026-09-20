<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class AdminSupplierController extends Controller
{
    #[OA\Get(
        path: '/api/admin/suppliers',
        summary: 'List all suppliers with statistics',
        tags: ['Admin Suppliers'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of suppliers with product and purchase order counts'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
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

    #[OA\Post(
        path: '/api/admin/suppliers',
        summary: 'Create a new medicine supplier',
        tags: ['Admin Suppliers'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Mega Lifesciences'),
                    new OA\Property(property: 'phone', type: 'string', example: '+959987654321'),
                    new OA\Property(property: 'address', type: 'string', example: 'Industrial Zone 1, Yangon')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Supplier created'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
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

    #[OA\Get(
        path: '/api/admin/suppliers/{id}',
        summary: 'Get supplier details with catalog and order history',
        tags: ['Admin Suppliers'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Supplier details'),
            new OA\Response(response: 404, description: 'Supplier not found')
        ]
    )]
    public function show($id)
    {
        return response()->json(Supplier::with(['products', 'purchases'])->findOrFail($id));
    }

    #[OA\Put(
        path: '/api/admin/suppliers/{id}',
        summary: 'Update supplier information',
        tags: ['Admin Suppliers'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Mega Lifesciences Ltd'),
                    new OA\Property(property: 'phone', type: 'string', example: '+959987654321'),
                    new OA\Property(property: 'address', type: 'string', example: 'New Address')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Supplier updated'),
            new OA\Response(response: 404, description: 'Supplier not found')
        ]
    )]
    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update($request->only(['name', 'phone', 'address']));
        ActivityLog::log('updated', 'Supplier', $id, "Supplier '{$supplier->name}' updated");

        return response()->json(['message' => 'Supplier updated', 'supplier' => $supplier]);
    }

    #[OA\Delete(
        path: '/api/admin/suppliers/{id}',
        summary: 'Delete supplier',
        tags: ['Admin Suppliers'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Supplier deleted'),
            new OA\Response(response: 404, description: 'Supplier not found')
        ]
    )]
    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();
        ActivityLog::log('deleted', 'Supplier', $id, "Supplier '{$supplier->name}' deleted");

        return response()->json(['message' => 'Supplier deleted']);
    }
}
