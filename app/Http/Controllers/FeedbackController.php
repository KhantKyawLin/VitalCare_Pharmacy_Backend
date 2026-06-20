<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    /**
     * List feedbacks for a health tip (public)
     */
    public function index($healthTipId)
    {
        $feedbacks = Feedback::with('user:id,name,profile')
            ->where('health_tip_id', $healthTipId)
            ->latest()
            ->get();

        return response()->json($feedbacks);
    }

    /**
     * Add feedback (authenticated users + admin)
     */
    public function store(Request $request, $healthTipId)
    {
        $validator = Validator::make($request->all(), [
            'comments' => 'required|string|max:1000',
            'rating' => 'required|integer|min:1|max:5',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $feedback = Feedback::create([
            'user_id' => auth('api')->id(),
            'health_tip_id' => $healthTipId,
            'comments' => $request->comments,
            'rating' => $request->rating,
        ]);

        return response()->json(['message' => 'Feedback submitted', 'feedback' => $feedback->load('user:id,name')], 201);
    }
}
