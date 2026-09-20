<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HealthTip;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class AdminHealthTipController extends Controller
{
    #[OA\Get(
        path: '/api/admin/health-tips',
        summary: 'List all health tips with engagement metrics (Admin/Pharmacist)',
        tags: ['Admin Health Tips'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated health tips with author details and metrics')
        ]
    )]
    public function index(Request $request)
    {
        $query = HealthTip::with(['author' => function($q) {
            $q->withCount('healthTips');
        }])->latest();
        
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $tips = $query->paginate($request->get('per_page', 15));
        
        // Add metrics
        $metrics = [
            'total_tips' => HealthTip::count(),
            'total_feedbacks' => \App\Models\Feedback::count(),
            'avg_rating' => \App\Models\Feedback::avg('rating') ?: 0,
            'authors_count' => \App\Models\User::whereHas('healthTips')->count()
        ];

        return response()->json([
            'data' => $tips->items(),
            'current_page' => $tips->currentPage(),
            'last_page' => $tips->lastPage(),
            'total' => $tips->total(),
            'metrics' => $metrics
        ]);
    }

    #[OA\Get(
        path: '/api/admin/health-tips/{id}',
        summary: 'Get health tip details with full feedback thread and rating breakdown',
        tags: ['Admin Health Tips'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Health tip details'),
            new OA\Response(response: 404, description: 'Health tip not found')
        ]
    )]
    public function show($id)
    {
        $tip = HealthTip::with(['author' => function($q) {
            $q->withCount('healthTips');
        }, 'feedbacks.user'])->findOrFail($id);
        
        // Get 3 related tips (latest other tips for now)
        $related = HealthTip::where('id', '!=', $id)->latest()->take(3)->get();
        
        return response()->json([
            'tip' => $tip,
            'related' => $related,
            'stats' => [
                'rating' => $tip->averageRating() ?: 0,
                'feedbacks_count' => $tip->feedbacks()->count()
            ]
        ]);
    }

    #[OA\Post(
        path: '/api/admin/health-tips',
        summary: 'Publish a new health article or wellness guide',
        tags: ['Admin Health Tips'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['title', 'content'],
                    properties: [
                        new OA\Property(property: 'title', type: 'string', example: 'Tips for Maintaining Blood Pressure'),
                        new OA\Property(property: 'content', type: 'string', example: 'Detailed medical advice here...'),
                        new OA\Property(property: 'is_published', type: 'boolean', example: true),
                        new OA\Property(property: 'image', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Health tip published'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,gif|max:2048',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $data = [
            'title' => $request->title,
            'content' => $request->content,
            'user_id' => auth('api')->id(),
            'is_published' => $request->has('is_published') ? (bool)$request->is_published : true,
        ];

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('health_tips', 'public');
        }

        $tip = HealthTip::create($data);
        ActivityLog::log('created', 'HealthTip', $tip->id, "Health tip '{$tip->title}' created");

        return response()->json(['message' => 'Health tip created', 'health_tip' => $tip], 201);
    }

    #[OA\Put(
        path: '/api/admin/health-tips/{id}',
        summary: 'Update health tip article and image',
        tags: ['Admin Health Tips'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'title', type: 'string', example: 'Updated Article Title'),
                        new OA\Property(property: 'content', type: 'string', example: 'Updated body text'),
                        new OA\Property(property: 'is_published', type: 'boolean', example: true),
                        new OA\Property(property: 'image', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Health tip updated'),
            new OA\Response(response: 404, description: 'Health tip not found')
        ]
    )]
    public function update(Request $request, $id)
    {
        $tip = HealthTip::findOrFail($id);

        $data = $request->only(['title', 'content']);
        if ($request->has('is_published')) {
            $data['is_published'] = (bool)$request->is_published;
        }
        
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('health_tips', 'public');
        }

        $tip->update($data);
        ActivityLog::log('updated', 'HealthTip', $id, "Health tip '{$tip->title}' updated");

        return response()->json(['message' => 'Health tip updated', 'health_tip' => $tip]);
    }

    #[OA\Patch(
        path: '/api/admin/health-tips/{id}/toggle-status',
        summary: 'Toggle public publishing status of health tip',
        tags: ['Admin Health Tips'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Publish status toggled'),
            new OA\Response(response: 404, description: 'Health tip not found')
        ]
    )]
    public function toggleStatus($id)
    {
        $tip = HealthTip::findOrFail($id);
        $tip->is_published = !$tip->is_published;
        $tip->save();

        $status = $tip->is_published ? 'Published' : 'Draft';
        ActivityLog::log('updated', 'HealthTip', $id, "Health tip '{$tip->title}' visibility toggled to {$status}");

        return response()->json(['message' => 'Status updated', 'is_published' => $tip->is_published]);
    }

    #[OA\Delete(
        path: '/api/admin/health-tips/{id}',
        summary: 'Delete health tip',
        tags: ['Admin Health Tips'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Health tip deleted'),
            new OA\Response(response: 404, description: 'Health tip not found')
        ]
    )]
    public function destroy($id)
    {
        $tip = HealthTip::findOrFail($id);
        $tip->delete();
        ActivityLog::log('deleted', 'HealthTip', $id, "Health tip '{$tip->title}' deleted");

        return response()->json(['message' => 'Health tip deleted']);
    }
}
