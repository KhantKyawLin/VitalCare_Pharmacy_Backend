<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class FeedbackController extends Controller
{
    #[OA\Get(
        path: '/api/health-tips/{id}/feedbacks',
        summary: 'List user reviews and feedback for a specific health tip',
        tags: ['Health Tips & FAQs (Public)'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of feedbacks and ratings')
        ]
    )]
    public function index($healthTipId)
    {
        $feedbacks = Feedback::with('user:id,name,profile')
            ->where('health_tip_id', $healthTipId)
            ->latest()
            ->get();

        return response()->json($feedbacks);
    }

    #[OA\Post(
        path: '/api/health-tips/{id}/feedback',
        summary: 'Post feedback and rating (1-5) on a health tip',
        tags: ['Health Tips & FAQs (Public)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['comments', 'rating'],
                properties: [
                    new OA\Property(property: 'comments', type: 'string', example: 'Very informative tip, thank you!'),
                    new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5, example: 5),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Feedback submitted'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
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
