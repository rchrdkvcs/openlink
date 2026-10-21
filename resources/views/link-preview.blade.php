<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $preview->title }}</title>
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $preview->title }}">
<meta name="twitter:title" content="{{ $preview->title }}">
@if ($preview->description)
<meta name="description" content="{{ $preview->description }}">
<meta property="og:description" content="{{ $preview->description }}">
<meta name="twitter:description" content="{{ $preview->description }}">
@endif
@if ($preview->url)
<meta property="og:url" content="{{ $preview->url }}">
@endif
@if ($preview->imageUrl)
<meta property="og:image" content="{{ $preview->imageUrl }}">
<meta name="twitter:image" content="{{ $preview->imageUrl }}">
<meta name="twitter:card" content="summary_large_image">
@else
<meta name="twitter:card" content="summary">
@endif
</head>
<body>
<h1>{{ $preview->title }}</h1>
@if ($preview->description)
<p>{{ $preview->description }}</p>
@endif
</body>
</html>
