<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HealthTip;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminHealthTipController extends Controller
{
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

    public function toggleStatus($id)
    {
        $tip = HealthTip::findOrFail($id);
        $tip->is_published = !$tip->is_published;
        $tip->save();

        ActivityLog::log('updated', 'HealthTip', $id, "Health tip '{$tip->title}' visibility toggled to " . ($tip->is_published ? 'Published' : 'Draft'));

        return response()->json(['message' => 'Status updated', 'is_published' => $tip->is_published]);
    }

    public function destroy($id)
    {
        $tip = HealthTip::findOrFail($id);
        $tip->delete();
        ActivityLog::log('deleted', 'HealthTip', $id, "Health tip '{$tip->title}' deleted");

        return response()->json(['message' => 'Health tip deleted']);
    }
}
