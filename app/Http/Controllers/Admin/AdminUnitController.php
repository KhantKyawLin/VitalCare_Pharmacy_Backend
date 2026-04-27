<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminUnitController extends Controller
{
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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:units,name',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $unit = Unit::create(['name' => $request->name]);
        ActivityLog::log('created', 'Unit', $unit->id, "Unit '{$unit->name}' created");

        return response()->json(['message' => 'Unit created', 'unit' => $unit], 201);
    }

    public function update(Request $request, $id)
    {
        $unit = Unit::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:units,name,' . $id,
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $old = $unit->name;
        $unit->update(['name' => $request->name]);
        ActivityLog::log('updated', 'Unit', $id, "Unit renamed from '{$old}' to '{$request->name}'");

        return response()->json(['message' => 'Unit updated', 'unit' => $unit]);
    }

    public function destroy($id)
    {
        $unit = Unit::findOrFail($id);
        $unit->delete();
        ActivityLog::log('deleted', 'Unit', $id, "Unit '{$unit->name}' deleted");

        return response()->json(['message' => 'Unit deleted']);
    }
}
