<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class AdminUnitController extends Controller
{
    #[OA\Get(
        path: '/api/admin/units',
        summary: 'List all units with statistics',
        tags: ['Units'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of units with product count and most used unit metric',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'units', type: 'array', items: new OA\Items(type: 'object')),
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
        $units = Unit::withCount('products')->get();
        
        $totalUnits = $units->count();
        $mostUsedUnit = $units->sortByDesc('products_count')->first();
        
        return response()->json([
            'units' => $units,
            'stats' => [
                'total_units' => $totalUnits,
                'most_used_unit' => $mostUsedUnit ? [
                    'name' => $mostUsedUnit->name,
                    'count' => $mostUsedUnit->products_count
                ] : null
            ]
        ]);
    }

    #[OA\Post(
        path: '/api/admin/units',
        summary: 'Create single unit or bulk units',
        description: 'Provide "name" for single creation, or "units" array for bulk creation with duplicate control.',
        tags: ['Units'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Strip', description: 'Used for single creation'),
                    new OA\Property(
                        property: 'units',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['Strip', 'Box', 'Bottle', 'Piece'],
                        description: 'Used for bulk creation'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Unit or units created successfully'),
            new OA\Response(response: 200, description: 'Partial creation (some succeeded, duplicates failed)'),
            new OA\Response(response: 422, description: 'Validation error or already present'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function store(Request $request)
    {
        // Bulk create: { units: ['Strip', 'Box', ...] }
        if ($request->has('units') && is_array($request->units)) {
            $validator = Validator::make($request->all(), [
                'units' => 'required|array|min:1',
                'units.*' => 'required|string|max:50',
            ]);
            if ($validator->fails()) return response()->json($validator->errors(), 422);

            $created = [];
            $failedNames = [];
            $seenInBatch = [];

            foreach ($request->units as $rawName) {
                $name = trim($rawName);
                if (empty($name)) continue;

                $lower = strtolower($name);

                // Check duplicate in this batch or already in database
                if (in_array($lower, $seenInBatch) || Unit::whereRaw('LOWER(name) = ?', [$lower])->exists()) {
                    if (!in_array($name, $failedNames)) {
                        $failedNames[] = $name;
                    }
                    continue;
                }

                $seenInBatch[] = $lower;
                $unit = Unit::create(['name' => $name]);
                ActivityLog::log('created', 'Unit', $unit->id, "Unit '{$unit->name}' created");
                $created[] = $unit;
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
                    'units' => $created,
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
                'message' => "{$successCount} unit" . ($successCount === 1 ? '' : 's') . " created successfully.",
                'units' => $created,
            ], 201);
        }

        // Single create: { name: 'Name' }
        $name = trim($request->name);
        $validator = Validator::make(['name' => $name], [
            'name' => 'required|string|max:50|unique:units,name',
        ], [
            'name.unique' => "The unit name '{$name}' is already present.",
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "The unit name '{$name}' is already present.",
                'errors' => $validator->errors()
            ], 422);
        }

        $unit = Unit::create(['name' => $name]);
        ActivityLog::log('created', 'Unit', $unit->id, "Unit '{$unit->name}' created");

        return response()->json([
            'status' => 'success',
            'message' => 'Unit created successfully.',
            'unit' => $unit
        ], 201);
    }

    #[OA\Put(
        path: '/api/admin/units/{id}',
        summary: 'Update unit name',
        tags: ['Units'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Bottle')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Unit updated'),
            new OA\Response(response: 422, description: 'Validation error or unit name already present'),
            new OA\Response(response: 404, description: 'Unit not found')
        ]
    )]
    public function update(Request $request, $id)
    {
        $unit = Unit::findOrFail($id);
        $name = trim($request->name);
        $validator = Validator::make(['name' => $name], [
            'name' => 'required|string|max:50|unique:units,name,' . $id,
        ], [
            'name.unique' => "The unit name '{$name}' is already present.",
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "The unit name '{$name}' is already present.",
                'errors' => $validator->errors()
            ], 422);
        }

        $old = $unit->name;
        $unit->update(['name' => $name]);
        ActivityLog::log('updated', 'Unit', $id, "Unit renamed from '{$old}' to '{$name}'");

        return response()->json(['message' => 'Unit updated', 'unit' => $unit]);
    }

    #[OA\Delete(
        path: '/api/admin/units/{id}',
        summary: 'Delete unit',
        tags: ['Units'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Unit deleted'),
            new OA\Response(response: 404, description: 'Unit not found')
        ]
    )]
    public function destroy($id)
    {
        $unit = Unit::findOrFail($id);
        $unit->delete();
        ActivityLog::log('deleted', 'Unit', $id, "Unit '{$unit->name}' deleted");

        return response()->json(['message' => 'Unit deleted']);
    }
}
