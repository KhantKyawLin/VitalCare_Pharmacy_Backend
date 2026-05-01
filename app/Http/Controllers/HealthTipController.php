<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HealthTip;

class HealthTipController extends Controller
{
    public function index()
    {
        $tips = HealthTip::with('author')
            ->where('is_published', true)
            ->latest()
            ->get();
        return response()->json($tips);
    }

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
