<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HealthTip;
use OpenApi\Attributes as OA;

class HealthTipController extends Controller
{
    #[OA\Get(
        path: '/api/health-tips',
        summary: 'List published health tips and wellness advice for public users',
        tags: ['Health Tips & FAQs (Public)'],
        responses: [
            new OA\Response(response: 200, description: 'List of published health tips')
        ]
    )]
    public function index()
    {
        $tips = HealthTip::with('author')
            ->where('is_published', true)
            ->latest()
            ->get();
        return response()->json($tips);
    }

    #[OA\Get(
        path: '/api/health-tips/{id}',
        summary: 'Get health tip details with author info, feedbacks, and related tips',
        tags: ['Health Tips & FAQs (Public)'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Health tip details, feedbacks, and recommendations'),
            new OA\Response(response: 404, description: 'Health tip not found')
        ]
    )]
    public function show($id)
    {
        $tip = HealthTip::with(['author', 'feedbacks.user'])
            ->where('is_published', true)
            ->findOrFail($id);

        $related = HealthTip::where('id', '!=', $id)
            ->where('is_published', true)
            ->latest()
            ->take(3)
            ->get();

        // Popular tips based on rating or just latest for now but named popular
        $popular = HealthTip::withCount('feedbacks')
            ->where('is_published', true)
            ->orderBy('feedbacks_count', 'desc')
            ->take(3)
            ->get();

        return response()->json([
            'tip' => $tip,
            'related' => $related,
            'popular' => $popular
        ]);
    }
}
