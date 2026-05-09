<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AdminFaqController extends Controller
{
    public function index()
    {
        return response()->json(Faq::orderBy('order')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'is_published' => 'boolean',
            'order' => 'integer'
        ]);

        $faq = Faq::create($request->all());
        
        ActivityLog::log('created', 'Faq', $faq->id, "New FAQ created: " . $faq->question);

        return response()->json($faq);
    }

    public function update(Request $request, $id)
    {
        $faq = Faq::findOrFail($id);
        $old = $faq->toArray();

        $request->validate([
            'question' => 'sometimes|required|string|max:255',
            'answer' => 'sometimes|required|string',
            'is_published' => 'boolean',
            'order' => 'integer'
        ]);

        $faq->update($request->all());
        
        ActivityLog::log('updated', 'Faq', $faq->id, "FAQ updated: " . $faq->question, $old, $faq->toArray());

        return response()->json($faq);
    }

    public function destroy($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->delete();
        
        ActivityLog::log('deleted', 'Faq', $id, "FAQ deleted: " . $faq->question);

        return response()->json(['message' => 'FAQ deleted successfully']);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:faqs,id'
        ]);

        foreach ($request->ids as $index => $id) {
            Faq::where('id', $id)->update(['order' => $index]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }
}
