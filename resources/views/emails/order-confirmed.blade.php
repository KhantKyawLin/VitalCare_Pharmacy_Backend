<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; border: 1px solid #eee; border-radius: 10px; overflow: hidden; }
        .header { background: #8DB600; color: #fff; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .order-info { background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; border-bottom: 2px solid #eee; padding: 10px 0; }
        .table td { padding: 10px 0; border-bottom: 1px solid #eee; }
        .footer { background: #333; color: #ccc; padding: 20px; text-align: center; font-size: 12px; }
        .btn { display: inline-block; padding: 12px 25px; background: #8DB600; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 20px; }
        .total-row { font-size: 18px; font-weight: bold; color: #8DB600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Vital Care Pharmacy</h1>
            <p>Thank you for your order!</p>
        </div>
        <div class="content">
            <h2>Hi {{ $order->user->name }},</h2>
            <p>We've received your order and are getting it ready. You'll receive another update when your package ships.</p>
            
            <div class="order-info">
                <strong>Order Number:</strong> {{ $order->receipt_number }}<br>
                <strong>Date:</strong> {{ $order->created_at->format('M d, Y h:i A') }}<br>
                <strong>Payment Method:</strong> {{ strtoupper($order->payment_method) }}
            </div>

            <h3>Order Summary</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->orderProducts as $item)
                    <tr>
                        <td>{{ $item->product->name }}</td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                        <td style="text-align: right;">Ks. {{ number_format($item->price * $item->quantity, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr>
                        <td colspan="2" style="text-align: right; padding-top: 20px;">Subtotal:</td>
                        <td style="text-align: right; padding-top: 20px;">Ks. {{ number_format($order->total_amount + $order->discount_amount - $order->tax_amount, 2) }}</td>
                    </tr>
                    @if($order->discount_amount > 0)
                    <tr>
                        <td colspan="2" style="text-align: right;">Discount:</td>
                        <td style="text-align: right; color: red;">-Ks. {{ number_format($order->discount_amount, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <td colspan="2" style="text-align: right; padding-top: 10px;">Total:</td>
                        <td style="text-align: right; padding-top: 10px;">Ks. {{ number_format($order->total_amount, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div style="text-align: center;">
                <a href="http://localhost:5173/profile/orders" class="btn">View Order Status</a>
            </div>
            
            <p style="margin-top: 30px;">If you have any questions, please reply to this email or contact us at {{ env('ADMIN_EMAIL', 'support@vitalcare.com') }}.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Vital Care Pharmacy. No.(410) Padonmar Street, Yangon.<br>
            Trust. Care. Wellness.
        </div>
    </div>
</body>
</html>
