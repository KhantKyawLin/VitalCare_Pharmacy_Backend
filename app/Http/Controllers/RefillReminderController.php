<?php

namespace App\Http\Controllers;

use App\Models\RefillReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RefillReminderController extends Controller
{
    /**
     * Get pending reminders for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $reminders = RefillReminder::with(['product' => function ($query) {
                $query->select('id', 'name', 'price', 'image');
            }])
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'sent'])
            ->orderBy('due_date', 'asc')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $reminders
        ]);
    }

    /**
     * Handle actions (actioned/ignored) on a reminder.
     */
    public function handleAction(Request $request, RefillReminder $reminder)
    {
        $user = Auth::user();

        if ($reminder->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to this reminder.'
            ], 403);
        }

        $request->validate([
            'action' => 'required|in:actioned,ignored',
            'notes' => 'nullable|string'
        ]);

        $reminder->status = $request->input('action');
        if ($request->has('notes')) {
            $reminder->notes = $request->input('notes');
        }
        $reminder->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Reminder updated successfully.',
            'data' => $reminder
        ]);
    }
}
