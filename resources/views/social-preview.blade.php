<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Primary Meta Tags -->
    <title>{{ $title }}</title>
    <meta name="title" content="{{ $title }}">
    <meta name="description" content="{{ $description }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    @if(isset($image))
    <meta property="og:image" content="{{ $image }}">
    @endif

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ $url }}">
    <meta property="twitter:title" content="{{ $title }}">
    <meta property="twitter:description" content="{{ $description }}">
    @if(isset($image))
    <meta property="twitter:image" content="{{ $image }}">
    @endif

    @if(isset($price))
    <meta property="product:price:amount" content="{{ $price }}">
    <meta property="og:price:amount" content="{{ $price }}">
    @endif

    <!-- Redirect to Frontend -->
    <script>
        window.location.href = "{{ $url }}";
    </script>
    
    <!-- Fallback Redirect -->
    <meta http-equiv="refresh" content="0; url={{ $url }}">
</head>
<body>
    <p>Redirecting to {{ $title }}...</p>
</body>
</html>
