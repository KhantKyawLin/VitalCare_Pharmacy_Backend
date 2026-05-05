<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminActivityLogController extends Controller
{
    /**
     * Fetch activity logs with filtering and pagination.
     */
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

    /**
     * Get available users and action types for filtering.
     */
    public function filters()
    {
        $users = User::select('id', 'name', 'role')->whereIn('role', ['admin', 'staff', 'pharmacist', 'superadmin'])->get();
        $actions = ActivityLog::distinct()->pluck('action');

        return response()->json([
            'users' => $users,
            'actions' => $actions
        ]);
    }

    /**
     * Delete old logs (Cleanup).
     */
    public function cleanup(Request $request)
    {
        $days = $request->get('days', 30);
        $count = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'message' => "Successfully cleaned up $count logs older than $days days."
        ]);
    }
}
