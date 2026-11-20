{{-- Runs before first paint so the page never flashes the wrong theme. Keep in sync with resources/js/theme.js. --}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (function () {
        var theme = 'light';
        try {
            var saved = localStorage.getItem('theme');
            theme = saved === 'dark' || saved === 'light'
                ? saved
                : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        } catch (e) {}
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>
