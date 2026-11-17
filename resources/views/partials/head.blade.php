<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<script>
    // Light / dark theme: the user's choice (theme button) wins; otherwise follow the system.
    // Runs before the page paints, so there is no flash of the wrong theme.
    (function () {
        var root = document.documentElement, saved = null;
        try { saved = localStorage.getItem('theme'); } catch (e) {}
        if (saved === 'light' || saved === 'dark') root.dataset.theme = saved;

        function current() {
            if (root.dataset.theme) return root.dataset.theme;
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        function label() {
            var next = current() === 'dark' ? 'light' : 'dark';
            document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
                b.setAttribute('aria-label', 'Switch to ' + next + ' theme');
                b.title = 'Switch to ' + next + ' theme';
            });
        }
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-theme-toggle]');
            if (!btn) return;
            var next = current() === 'dark' ? 'light' : 'dark';
            root.dataset.theme = next;
            try { localStorage.setItem('theme', next); } catch (e) {}
            label();
        });
        document.addEventListener('DOMContentLoaded', label);
    })();
</script>
<title>@yield('title', 'TIF PMO')</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@hasSection('css')
    <style>@yield('css')</style>
@endif
