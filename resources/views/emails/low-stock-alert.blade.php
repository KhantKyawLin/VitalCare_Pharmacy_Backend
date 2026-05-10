<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #444; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-top: 5px solid #ff4d4f; padding: 30px; border-radius: 8px; }
        .warning-box { background: #fff1f0; border: 1px solid #ffa39e; padding: 15px; border-radius: 4px; color: #cf1322; font-weight: bold; margin-bottom: 25px; text-align: center; }
        .label { font-weight: bold; color: #666; text-transform: uppercase; font-size: 11px; display: block; margin-bottom: 5px; }
        .value { margin-bottom: 20px; font-size: 18px; color: #000; font-weight: bold; }
        .stock-value { font-size: 24px; color: #ff4d4f; }
        .footer { margin-top: 30px; font-size: 12px; color: #999; border-top: 1px solid #eee; padding-top: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #ff4d4f; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="warning-box">
            ⚠️ LOW STOCK ALERT
        </div>
        
        <p>Attention Admin, the following product has reached a critical stock level and requires immediate reordering.</p>
        
        <div style="margin-top: 30px;">
            <span class="label">Product Name</span>
            <div class="value">{{ $product->name }}</div>

            <span class="label">SKU / Code</span>
            <div class="value">{{ $product->id }}</div>

            <span class="label">Current Stock Level</span>
            <div class="value stock-value">{{ $currentStock }} units</div>

            <span class="label">Minimum Threshold</span>
            <div class="value">{{ $product->min_stock_level }} units</div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="http://localhost:5173/admin/reorder-alerts" class="btn">Process Reorder</a>
        </div>

        <div class="footer">
            This is an automated inventory system alert. Please log in to the <a href="http://localhost:5173/admin">Admin Dashboard</a> to manage your stock.
        </div>
    </div>
</body>
</html>
