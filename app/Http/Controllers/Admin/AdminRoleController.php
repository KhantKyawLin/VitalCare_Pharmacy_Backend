<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class AdminRoleController extends Controller
{
    #[OA\Get(
        path: '/api/admin/roles',
        summary: 'List user roles and permission sets',
        tags: ['Admin Roles & Permissions'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of roles with mapped permissions and user counts')
        ]
    )]
    public function index()
    {
        return response()->json(Role::with('permissions')->withCount('users')->get());
    }

    #[OA\Post(
        path: '/api/admin/roles',
        summary: 'Create a custom role with permissions',
        tags: ['Admin Roles & Permissions'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Inventory Manager'),
                    new OA\Property(property: 'description', type: 'string', example: 'Can manage purchases, batches, and suppliers'),
                    new OA\Property(property: 'permission_ids', type: 'array', items: new OA\Items(type: 'integer', example: 1)),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Role created'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
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

    #[OA\Put(
        path: '/api/admin/roles/{id}',
        summary: 'Update role details and sync permissions',
        tags: ['Admin Roles & Permissions'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Lead Pharmacist'),
                    new OA\Property(property: 'description', type: 'string', example: 'Updated description'),
                    new OA\Property(property: 'permission_ids', type: 'array', items: new OA\Items(type: 'integer', example: 2)),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Role updated'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Role not found')
        ]
    )]
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

    #[OA\Delete(
        path: '/api/admin/roles/{id}',
        summary: 'Delete role (Cannot delete system roles: admin, user)',
        tags: ['Admin Roles & Permissions'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role deleted'),
            new OA\Response(response: 403, description: 'Cannot delete system roles'),
            new OA\Response(response: 404, description: 'Role not found')
        ]
    )]
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

    #[OA\Get(
        path: '/api/admin/permissions',
        summary: 'List all granular system permissions',
        tags: ['Admin Roles & Permissions'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of all system permissions')
        ]
    )]
    public function permissions()
    {
        return response()->json(Permission::all());
    }
}
