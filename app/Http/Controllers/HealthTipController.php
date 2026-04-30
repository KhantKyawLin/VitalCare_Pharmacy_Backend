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
        $tip = HealthTip::with('author')
            ->where('is_published', true)
            ->findOrFail($id);

        $related = HealthTip::where('id', '!=', $id)
            ->where('is_published', true)
            ->latest()
            ->take(3)
            ->get();

        return response()->json([
            'tip' => $tip,
            'related' => $related
        ]);
    }
}
