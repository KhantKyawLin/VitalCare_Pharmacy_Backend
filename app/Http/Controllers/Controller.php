<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "VitalCare Pharmacy API Documentation",
    description: "Interactive REST API documentation for VitalCare Pharmacy. Explore endpoints, test requests directly, and inspect schemas.",
    contact: new OA\Contact(name: "VitalCare Team", email: "admin@vitalcare.com")
)]
#[OA\Server(
    url: "/",
    description: "Current Host & Port (Auto-detect)"
)]
#[OA\Server(
    url: "http://127.0.0.1:8000",
    description: "Local Server (127.0.0.1:8000)"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Enter your JWT token in the format: Bearer <your-token>"
)]
abstract class Controller
{
    //
}
