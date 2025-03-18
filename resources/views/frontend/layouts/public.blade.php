<!-- resources\views\frontend\layouts\public.blade.php -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('description')">
    <meta property="og:image" content="@yield('og:image')">
    <title>@yield('title') - {{ config('app.name') }}</title>

    @yield('ld-data')

    {{-- Style --}}
    @include('frontend.partials.public-styles')
    @yield('css')

    {{-- Custom CSS --}}
    {!! $setting->header_css !!}
    {!! $setting->header_script !!}

    <style>
        /* Sticky Header Styles */
        .sticky-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 999;
            background-color: #fff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Offset main content to avoid overlap */
        body {
            padding-top: 70px; /* Adjust according to your header height */
        }
    </style>
</head>

<body dir="{{ langDirection() }}">
    <input type="hidden" value="{{ current_country_code() }}" id="current_country_code">
    @php
        $userId = auth()->check() ? auth()->id() : 0;
    @endphp
    <input type="hidden" id="auth_user" value="{{ $userId > 0 }}">
    <input type="hidden" id="auth_user_id" value="{{ $userId }}">

    <x-admin.app-mode-alert />

    <!-- Sticky Header -->
    <header class="sticky-header">
        @include('frontend.partials.header')
    </header>

    {{-- Main Content --}}
    <main>
        @yield('main')
    </main>

    {{-- Footer --}}
    @include('frontend.partials.footer')

    <!-- Scripts -->
    @include('frontend.partials.public-scripts')
</body>

</html>
