<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $order->receipt_number ?: $order->id }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #333; font-size: 13px; line-height: 1.5; margin: 0; padding: 0; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; }
        
        .header { margin-bottom: 30px; border-bottom: 2px solid #8DB600; padding-bottom: 20px; }
        .logo { width: 150px; height: auto; margin-bottom: 10px; }
        .company-info { float: right; text-align: right; }
        .company-name { color: #8DB600; font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        
        .clearfix { clear: both; }
        
        .invoice-details { margin-bottom: 30px; }
        .bill-to { float: left; width: 50%; }
        .invoice-info { float: right; width: 50%; text-align: right; }
        
        .section-title { background: #f4f4f4; padding: 8px 12px; font-weight: bold; text-transform: uppercase; font-size: 11px; color: #666; margin-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table th { background: #8DB600; color: #fff; text-align: left; padding: 10px; text-transform: uppercase; font-size: 11px; }
        table td { padding: 10px; border-bottom: 1px solid #eee; }
        
        .totals { float: right; width: 40%; }
        .total-row { padding: 5px 0; }
        .total-label { float: left; font-weight: bold; }
        .total-value { float: right; }
        .grand-total { border-top: 2px solid #8DB600; margin-top: 10px; padding-top: 10px; font-size: 16px; font-weight: bold; color: #8DB600; }
        
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; border-top: 1px solid #eee; padding-top: 20px; font-size: 10px; color: #999; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 10px; text-transform: uppercase; }
        .paid { background: #e6fffa; color: #008080; }
        .unpaid { background: #fff1f0; color: #cf1322; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="header">
            <div class="company-info">
                <div class="company-name">{{ $settings['site_name'] ?? 'Vital Care Pharmacy' }}</div>
                <div>No.(410) Padonmar Street, Yangon</div>
                <div>Phone: +95 9 123 456 789</div>
                <div>Email: info@vitalcare.com</div>
            </div>
            <div class="logo-container">
                @if($logo && file_exists($logo))
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logo)) }}" class="logo">
                @else
                    <div class="company-name">VITAL CARE</div>
                @endif
            </div>
            <div class="clearfix"></div>
        </div>

        <div class="invoice-details">
            <div class="bill-to">
                <div class="section-title">Bill To</div>
                @if($order->order_type === 'walk-in')
                    <strong>Walk-in Customer</strong><br>
                    Reference: POS Transaction
                @else
                    <strong>{{ $order->user->name ?? 'Customer' }}</strong><br>
                    {{ $order->delivery_address }}<br>
                    Phone: {{ $order->contact_phone }}
                @endif
            </div>
            <div class="invoice-info">
                <div class="section-title">Invoice Info</div>
                <strong>Invoice #:</strong> {{ $order->receipt_number ?: $order->id }}<br>
                <strong>Date:</strong> {{ $order->created_at->format('d M Y') }}<br>
                <strong>Payment:</strong> {{ strtoupper($order->payment_method) }}<br>
                <strong>Status:</strong> 
                <span class="status-badge {{ $order->payment_status === 'paid' ? 'paid' : 'unpaid' }}">
                    {{ $order->payment_status ?: 'PENDING' }}
                </span>
            </div>
            <div class="clearfix"></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">Item Description</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Price</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->orderProducts as $item)
                <tr>
                    <td>
                        {{ $item->product->name }}
                        @if($item->is_gift)
                            <br><small style="color: #8DB600; font-weight: bold;">(FREE GIFT)</small>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">Ks. {{ number_format($item->price, 2) }}</td>
                    <td style="text-align: right;">Ks. {{ number_format($item->price * $item->quantity, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="total-row">
                <span class="total-label">Subtotal</span>
                <span class="total-value">Ks. {{ number_format($order->total_amount + $order->discount_amount - $order->tax_amount, 2) }}</span>
                <div class="clearfix"></div>
            </div>
            @if($order->discount_amount > 0)
            <div class="total-row">
                <span class="total-label">Discount</span>
                <span class="total-value">-Ks. {{ number_format($order->discount_amount, 2) }}</span>
                <div class="clearfix"></div>
            </div>
            @endif
            @if($order->tax_amount > 0)
            <div class="total-row">
                <span class="total-label">Tax (5%)</span>
                <span class="total-value">Ks. {{ number_format($order->tax_amount, 2) }}</span>
                <div class="clearfix"></div>
            </div>
            @endif
            <div class="total-row grand-total">
                <span class="total-label">Grand Total</span>
                <span class="total-value">Ks. {{ number_format($order->total_amount, 2) }}</span>
                <div class="clearfix"></div>
            </div>
        </div>
        <div class="clearfix"></div>

        <div style="margin-top: 50px; font-style: italic; color: #666;">
            <p><strong>Note:</strong> Medicines once sold are not returnable unless expired or damaged at the time of delivery. Please keep this invoice for your records.</p>
        </div>

        <div class="footer">
            Thank you for choosing Vital Care Pharmacy!<br>
            Trust. Care. Wellness.
        </div>
    </div>
</body>
</html>
