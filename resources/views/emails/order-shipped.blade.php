<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; border: 1px solid #eee; border-radius: 10px; overflow: hidden; }
        .header { background: #008080; color: #fff; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .status-badge { display: inline-block; padding: 5px 15px; background: #e6fffa; color: #008080; border-radius: 20px; font-weight: bold; font-size: 14px; margin-bottom: 20px; text-transform: uppercase; }
        .order-info { background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; border-bottom: 2px solid #eee; padding: 10px 0; }
        .table td { padding: 10px 0; border-bottom: 1px solid #eee; }
        .footer { background: #333; color: #ccc; padding: 20px; text-align: center; font-size: 12px; }
        .btn { display: inline-block; padding: 12px 25px; background: #008080; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Vital Care Pharmacy</h1>
            <p>Your package is on its way!</p>
        </div>
        <div class="content">
            <div class="status-badge">Order Shipped</div>
            <h2>Hi {{ $order->user->name }},</h2>
            <p>Great news! Your order <strong>#{{ $order->receipt_number }}</strong> has been shipped and is now heading to you.</p>
            
            <div class="order-info">
                <strong>Delivery Address:</strong><br>
                {{ $order->delivery_address }}<br>
                <strong>Contact Phone:</strong> {{ $order->contact_phone }}
            </div>

            <h3>Package Contents</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align: center;">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->orderProducts as $item)
                    <tr>
                        <td>{{ $item->product->name }}</td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="text-align: center;">
                <a href="http://localhost:5173/profile/orders" class="btn">Track My Order</a>
            </div>
            
            <p style="margin-top: 30px;">Thank you for shopping with Vital Care Pharmacy!</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Vital Care Pharmacy. No.(410) Padonmar Street, Yangon.<br>
            Trust. Care. Wellness.
        </div>
    </div>
</body>
</html>
