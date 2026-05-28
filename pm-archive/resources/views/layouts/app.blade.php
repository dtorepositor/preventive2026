<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Preventive Maintenance - CMU ODT</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        @php
            $configuredAppUrl = rtrim((string) config('app.url'), '/');
            $configuredAppPath = trim((string) parse_url($configuredAppUrl, PHP_URL_PATH), '/');
            $requestHostUrl = request()->getSchemeAndHttpHost();
            $requestBasePath = trim(request()->getBaseUrl(), '/');
            $requestUriPath = trim((string) parse_url((string) request()->server('REQUEST_URI', ''), PHP_URL_PATH), '/');
            $requestUsesConfiguredPath = $configuredAppPath !== ''
                && ($requestUriPath === $configuredAppPath || str_starts_with($requestUriPath, $configuredAppPath . '/'));

            if ($requestBasePath !== '') {
                $appBaseUrl = rtrim($requestHostUrl . '/' . $requestBasePath, '/');
            } elseif ($requestUsesConfiguredPath) {
                $appBaseUrl = rtrim($requestHostUrl . '/' . $configuredAppPath, '/');
            } else {
                $appBaseUrl = $requestHostUrl;
            }
            $appUser = auth()->user() ? [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'role' => auth()->user()->role,
                'role_label' => auth()->user()->roleLabel(),
                'is_active' => (bool) auth()->user()->is_active,
                'permissions' => auth()->user()->permissions(),
            ] : null;
        @endphp
        window.AppConfig = {
            baseUrl: @json($appBaseUrl),
            user: @json($appUser),
            appName: @json(config('app.name')),
            version: @json(config('app.version')),
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Figtree', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen antialiased text-slate-800">
    <div id="app">@yield('content')</div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @if (session('success'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({ icon: 'success', title: 'Success!', text: "{{ session('success') }}" });
        });
    </script>
    @endif
    @if (session('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({ icon: 'error', title: 'Error', text: "{{ session('error') }}" });
        });
    </script>
    @endif
    <script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.delete-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (form.dataset.swalConfirmed === '1') {
                delete form.dataset.swalConfirmed;
                return;
            }
            e.preventDefault();
            var msg = form.dataset.message || 'Are you sure?';
            Swal.fire({
                title: 'Are you sure?',
                text: msg,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it'
            }).then(function(result) {
                if (result.isConfirmed) {
                    form.dataset.swalConfirmed = '1';
                    form.submit();
                }
            });
        });
    });
});
</script>
@stack('scripts')
</body>
</html>
