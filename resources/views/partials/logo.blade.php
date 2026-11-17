{{--
    Infranexia logo: full colour in the light theme, inverse (white) in the dark theme.
    Usage: @include('partials.logo', ['class' => 'brand-logo', 'width' => 150])
           'onDark' => true always uses the inverse logo (for a dark panel in either theme);
           'onLight' => true always uses the colour logo (for a panel that stays white).
--}}
@php
    $width = $width ?? 150;
    $height = (int) round($width * 265 / 960);
    $colourSrc = asset('images/infranexia-logo.png');
    $inverseSrc = asset('images/infranexia-logo-inverse.png');
    $alt = $alt ?? 'Infranexia';
@endphp
@if (! empty($onDark))
    <img class="{{ $class ?? '' }}" src="{{ $inverseSrc }}" alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}">
@elseif (! empty($onLight))
    <img class="{{ $class ?? '' }}" src="{{ $colourSrc }}" alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}">
@else
    {{-- Both are in the page; the theme (light/dark) decides which one shows. --}}
    <img class="{{ $class ?? '' }} logo-light" src="{{ $colourSrc }}" alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}">
    <img class="{{ $class ?? '' }} logo-dark" src="{{ $inverseSrc }}" alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}">
@endif
