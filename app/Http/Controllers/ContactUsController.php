<?php

namespace App\Http\Controllers;

use App\Models\ContactUs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class ContactUsController extends Controller
{
    #[OA\Post(
        path: '/api/contact',
        summary: 'Submit customer support message or inquiry',
        tags: ['Public Utilities'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'subject', 'message'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Aung Aung'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'aung@example.com'),
                    new OA\Property(property: 'subject', type: 'string', example: 'Order Inquiry'),
                    new OA\Property(property: 'order_id', type: 'string', example: 'ORD-12345'),
                    new OA\Property(property: 'message', type: 'string', example: 'When will my order arrive?'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Message sent successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'subject' => 'required|string|max:500',
            'order_id' => 'required_if:subject,Order Inquiry|nullable|string|max:100',
            'message' => 'required|string',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $contact = ContactUs::create([
            'user_id' => auth('api')->id(),
            'name' => $request->name,
            'email' => $request->email,
            'subject' => $request->subject,
            'order_id' => $request->order_id,
            'message' => $request->message,
        ]);

        // Send Email Alert to Admin
        try {
            $adminEmail = env('ADMIN_EMAIL', 'support@vitalcare.com');
            \Illuminate\Support\Facades\Mail::to($adminEmail)
                ->send(new \App\Mail\NewContactMessage($contact));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send contact email: " . $e->getMessage());
        }

        return response()->json(['message' => 'Message sent successfully'], 201);
    }

    #[OA\Get(
        path: '/api/admin/contact-messages',
        summary: 'List customer contact messages received (Admin only)',
        tags: ['Public Utilities'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of submitted contact inquiries')
        ]
    )]
    public function index()
    {
        return response()->json(ContactUs::latest()->get());
    }

    #[OA\Put(
        path: '/api/admin/contact-messages/{id}',
        summary: 'Update status of customer contact message',
        tags: ['Public Utilities'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['unread', 'read', 'replied'], example: 'replied')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Message status updated')
        ]
    )]
    public function update(Request $request, $id)
    {
        $contact = ContactUs::findOrFail($id);
        $contact->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated']);
    }
}
