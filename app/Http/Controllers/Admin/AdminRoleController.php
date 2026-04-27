<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminRoleController extends Controller
{
    public function index()
    {
        return response()->json(Role::with('permissions')->withCount('users')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:roles,name',
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $role = Role::create($request->only(['name', 'description']));

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->permission_ids);
        }

        ActivityLog::log('created', 'Role', $role->id, "Role '{$role->name}' created");

        return response()->json(['message' => 'Role created', 'role' => $role->load('permissions')], 201);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:50|unique:roles,name,' . $id,
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $role->update($request->only(['name', 'description']));

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->permission_ids);
        }

        ActivityLog::log('updated', 'Role', $id, "Role '{$role->name}' updated");

        return response()->json(['message' => 'Role updated', 'role' => $role->load('permissions')]);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // Prevent deleting system roles
        if (in_array($role->name, ['admin', 'user'])) {
            return response()->json(['error' => 'Cannot delete system roles'], 403);
        }

        $role->delete();
        ActivityLog::log('deleted', 'Role', $id, "Role '{$role->name}' deleted");

        return response()->json(['message' => 'Role deleted']);
    }

    public function permissions()
    {
        return response()->json(Permission::all());
    }
}
