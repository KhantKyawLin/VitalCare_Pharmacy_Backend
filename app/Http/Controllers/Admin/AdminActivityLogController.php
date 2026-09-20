<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class AdminActivityLogController extends Controller
{
    #[OA\Get(
        path: '/api/admin/activity-logs',
        summary: 'List system audit trails and user action logs',
        tags: ['Admin Activity Logs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'action', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'price_updated')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated activity log records'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->orderBy('created_at', 'desc');

        // Filter by user
        if ($request->has('user_id') && $request->user_id !== 'all') {
            $query->where('user_id', $request->user_id);
        }

        // Filter by action type
        if ($request->has('action') && $request->action !== 'all') {
            $query->where('action', 'LIKE', '%' . $request->action . '%');
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->start_date));
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->end_date));
        }

        // Search in description or ip
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'LIKE', "%$search%")
                  ->orWhere('ip_address', 'LIKE', "%$search%")
                  ->orWhere('model_type', 'LIKE', "%$search%");
            });
        }

        $logs = $query->paginate($request->get('per_page', 50));

        return response()->json($logs);
    }

    #[OA\Get(
        path: '/api/admin/activity-logs/filters',
        summary: 'Get list of staff users and recorded action types for filter dropdowns',
        tags: ['Admin Activity Logs'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Filter dropdown options')
        ]
    )]
    public function filters()
    {
        $users = User::select('id', 'name', 'role')->whereIn('role', ['admin', 'staff', 'pharmacist', 'superadmin'])->get();
        $actions = ActivityLog::distinct()->pluck('action');

        return response()->json([
            'users' => $users,
            'actions' => $actions
        ]);
    }

    #[OA\Delete(
        path: '/api/admin/activity-logs/cleanup',
        summary: 'Prune audit logs older than N days (Admin only)',
        tags: ['Admin Activity Logs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'days', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 30, example: 60))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cleaned up logs count')
        ]
    )]
    public function cleanup(Request $request)
    {
        $days = $request->get('days', 30);
        $count = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'message' => "Successfully cleaned up $count logs older than $days days."
        ]);
    }
}
