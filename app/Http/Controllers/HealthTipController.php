<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HealthTip;

class HealthTipController extends Controller
{
    public function index()
    {
        $tips = HealthTip::latest()->get();
        return response()->json($tips);
    }

    public function show($id)
    {
        $tip = HealthTip::find($id);
        if (!$tip) {
            return response()->json(['message' => 'Health tip not found'], 404);
        }
        return response()->json($tip);
    }
}
