<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base" content="{{ rtrim(url('/'), '/') }}">
    <title>{{ $pageTitle ?? 'Requesty' }}</title>

    <script src="{{ asset('js/theme.js') }}"></script>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">

    <!-- Fuentes e Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Estilos Base del Sistema -->
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">

    @if (!empty($extraCss))
        @foreach ((array) $extraCss as $css)
            <link rel="stylesheet" href="{{ asset($css) }}">
        @endforeach
    @endif

    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">

    @if (!empty($extraHeadScripts))
        @foreach ((array) $extraHeadScripts as $scriptUrl)
            <script src="{{ $scriptUrl }}"></script>
        @endforeach
    @endif
</head>
<body data-page="{{ $currentPage ?? '' }}">
