<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FaqController extends Controller
{
    #[OA\Get(
        path: '/api/faqs',
        summary: 'List published FAQs ordered by display position for public users',
        tags: ['Health Tips & FAQs (Public)'],
        responses: [
            new OA\Response(response: 200, description: 'List of frequently asked questions and answers')
        ]
    )]
    public function index()
    {
        return response()->json(
            Faq::where('is_published', true)
                ->orderBy('order')
                ->get()
        );
    }
}
