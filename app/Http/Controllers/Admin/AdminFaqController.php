<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminFaqController extends Controller
{
    #[OA\Get(
        path: '/api/admin/faqs',
        summary: 'List all FAQs including drafts for management (Admin/Pharmacist)',
        tags: ['Admin FAQs'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of all FAQs')
        ]
    )]
    public function index()
    {
        return response()->json(Faq::orderBy('order')->get());
    }

    #[OA\Post(
        path: '/api/admin/faqs',
        summary: 'Create a new frequently asked question and answer',
        tags: ['Admin FAQs'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['question', 'answer'],
                properties: [
                    new OA\Property(property: 'question', type: 'string', example: 'Do you deliver on weekends?'),
                    new OA\Property(property: 'answer', type: 'string', example: 'Yes, our delivery fleet runs 7 days a week.'),
                    new OA\Property(property: 'is_published', type: 'boolean', example: true),
                    new OA\Property(property: 'order', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'FAQ created'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
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

    #[OA\Put(
        path: '/api/admin/faqs/{id}',
        summary: 'Update FAQ question or answer',
        tags: ['Admin FAQs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'question', type: 'string', example: 'Updated question?'),
                    new OA\Property(property: 'answer', type: 'string', example: 'Updated answer text.'),
                    new OA\Property(property: 'is_published', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'FAQ updated'),
            new OA\Response(response: 404, description: 'FAQ not found')
        ]
    )]
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

    #[OA\Delete(
        path: '/api/admin/faqs/{id}',
        summary: 'Delete an FAQ',
        tags: ['Admin FAQs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'FAQ deleted'),
            new OA\Response(response: 404, description: 'FAQ not found')
        ]
    )]
    public function destroy($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->delete();
        
        ActivityLog::log('deleted', 'Faq', $id, "FAQ deleted: " . $faq->question);

        return response()->json(['message' => 'FAQ deleted successfully']);
    }

    #[OA\Post(
        path: '/api/admin/faqs/reorder',
        summary: 'Reorder FAQs display sequencing',
        tags: ['Admin FAQs'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer', example: 1))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'FAQ sequence updated')
        ]
    )]
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
