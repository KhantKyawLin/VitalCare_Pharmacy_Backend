<?php

namespace App\Http\Controllers;

use App\Models\ContactUs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactUsController extends Controller
{
    /**
     * Submit contact form (public).
     */
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

        return response()->json(['message' => 'Message sent successfully'], 201);
    }

    /**
     * Admin: list contact messages.
     */
    public function index()
    {
        return response()->json(ContactUs::latest()->get());
    }

    /**
     * Admin: update message status.
     */
    public function update(Request $request, $id)
    {
        $contact = ContactUs::findOrFail($id);
        $contact->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated']);
    }
}
