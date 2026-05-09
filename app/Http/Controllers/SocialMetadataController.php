<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\HealthTip;
use Illuminate\Http\Request;

class SocialMetadataController extends Controller
{
    /**
     * Intercept sharing requests and provide rich social metadata.
     * This is useful for crawlers (Facebook, Viber) that don't execute JS.
     */
    public function product($id)
    {
        $product = Product::with('pictures')->find($id);
        
        if (!$product) {
            return redirect('http://localhost:5173/products'); // Fallback to frontend
        }

        $title = $product->name . " | Vital Care Pharmacy";
        $description = substr(strip_tags($product->description), 0, 200);
        $image = $product->primary_image_url;
        $price = "Ks. " . number_format($product->price, 2);
        $url = "http://localhost:5173/products/{$id}";

        return view('social-preview', compact('title', 'description', 'image', 'price', 'url'));
    }

    public function healthTip($id)
    {
        $tip = HealthTip::find($id);
        
        if (!$tip) {
            return redirect('http://localhost:5173/health-tips');
        }

        $title = $tip->title . " | Health Tips";
        $description = substr(strip_tags($tip->content), 0, 200);
        $image = $tip->image_path ? "http://127.0.0.1:8000/storage/{$tip->image_path}" : null;
        $url = "http://localhost:5173/health-tips/{$id}";

        return view('social-preview', compact('title', 'description', 'image', 'url'));
    }
}
