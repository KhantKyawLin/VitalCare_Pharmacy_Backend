<?php

namespace App\Http\Controllers;

use App\Models\RefillReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class RefillReminderController extends Controller
{
    #[OA\Get(
        path: '/api/auth/refill-reminders',
        summary: 'Get active medicine refill reminders for current customer',
        tags: ['Refill Reminders'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of upcoming or pending refill reminders'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
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

    #[OA\Post(
        path: '/api/auth/refill-reminders/{reminder}/action',
        summary: 'Mark refill reminder as actioned or ignored',
        tags: ['Refill Reminders'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'reminder', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['action'],
                properties: [
                    new OA\Property(property: 'action', type: 'string', enum: ['actioned', 'ignored'], example: 'actioned'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Purchased directly from counter')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Reminder updated successfully'),
            new OA\Response(response: 403, description: 'Unauthorized access'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
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
