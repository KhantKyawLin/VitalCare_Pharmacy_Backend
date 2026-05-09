<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #444; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-top: 5px solid #8DB600; padding: 30px; border-radius: 8px; }
        .label { font-weight: bold; color: #8DB600; text-transform: uppercase; font-size: 11px; display: block; margin-bottom: 5px; }
        .value { margin-bottom: 20px; font-size: 15px; color: #333; }
        .message-box { background: #fdfdfd; border: 1px solid #f0f0f0; padding: 20px; border-radius: 4px; font-style: italic; }
        .footer { margin-top: 30px; font-size: 12px; color: #999; border-top: 1px solid #eee; pt: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="margin-top: 0;">New Contact Inquiry</h2>
        <p>A new message has been submitted via the <strong>Vital Care Pharmacy</strong> contact form.</p>
        
        <div style="margin-top: 30px;">
            <span class="label">Sender Name</span>
            <div class="value">{{ $contact->name }}</div>

            <span class="label">Email Address</span>
            <div class="value"><a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></div>

            <span class="label">Phone Number</span>
            <div class="value">{{ $contact->phone ?? 'Not provided' }}</div>

            <span class="label">Subject</span>
            <div class="value">{{ $contact->subject }}</div>

            <span class="label">Message</span>
            <div class="message-box">
                {{ $contact->message }}
            </div>
        </div>

        <div class="footer">
            This is an automated notification. Please log in to the <a href="http://localhost:5173/admin/messages">Admin Dashboard</a> to manage all inquiries.
        </div>
    </div>
</body>
</html>
