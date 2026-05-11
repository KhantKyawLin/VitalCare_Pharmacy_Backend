<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminInvoiceController extends Controller
{
    /**
     * Generate and stream/download a professional PDF invoice.
     * Note: Requires barryvdh/laravel-dompdf package.
     */
    public function generatePDF($id)
    {
        $user = auth()->user();
        $order = Order::with(['user', 'orderProducts.product', 'cashier'])->findOrFail($id);
        
        // Security Check: If not admin/staff, verify ownership
        if (!$user->hasRole('admin') && !$user->hasRole('staff')) {
            if ($order->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized access to this invoice.'], 403);
            }
        }

        $settings = SiteSetting::all()->pluck('value', 'key');

        // Prepare data for the view
        $data = [
            'order' => $order,
            'settings' => $settings,
            'logo' => $settings->get('site_logo') ? public_path('storage/' . $settings->get('site_logo')) : null,
        ];

        // Load the blade template and generate PDF
        $pdf = Pdf::loadView('pdf.invoice', $data);

        // Professional PDF settings
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'sans-serif'
        ]);

        $fileName = 'Invoice-' . ($order->receipt_number ?: $order->id) . '.pdf';

        return $pdf->stream($fileName);
    }
}
