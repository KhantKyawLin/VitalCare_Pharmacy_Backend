<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HealthTip;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminHealthTipController extends Controller
{
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
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('health_tips', 'public');
        }

        $tip->update($data);
        ActivityLog::log('updated', 'HealthTip', $id, "Health tip '{$tip->title}' updated");

        return response()->json(['message' => 'Health tip updated', 'health_tip' => $tip]);
    }

    public function destroy($id)
    {
        $tip = HealthTip::findOrFail($id);
        $tip->delete();
        ActivityLog::log('deleted', 'HealthTip', $id, "Health tip '{$tip->title}' deleted");

        return response()->json(['message' => 'Health tip deleted']);
    }
}
