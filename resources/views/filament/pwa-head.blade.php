{{-- Makes Bari installable on phones and registers the service worker (public/sw.js). --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#047857">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Bari">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<style>html:not(.bari-can-install) .bari-install-item { display: none !important; }</style>
<script>
    window.bari = window.bari || {};
    window.bari.isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    if ('serviceWorker' in navigator && ! window.bari.swRegistered) {
        window.bari.swRegistered = true;
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }

    // Android/desktop Chrome offer installation; keep the offer for the "Install app" menu item.
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        window.bari.installPrompt = event;
        document.documentElement.classList.add('bari-can-install');
    });

    window.bari.install = async () => {
        const prompt = window.bari.installPrompt;
        if (! prompt) return;
        prompt.prompt();
        await prompt.userChoice;
        window.bari.installPrompt = null;
        document.documentElement.classList.remove('bari-can-install');
    };
</script>
