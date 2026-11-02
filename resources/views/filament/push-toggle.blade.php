{{-- "Turn on/off notifications" in the user menu: subscribes this device to app notifications. --}}
<meta name="vapid-public-key" content="{{ \App\Support\VapidKeys::get()['publicKey'] }}">
<style>
    html:not(.bari-push-supported) .bari-push-on-item,
    html:not(.bari-push-supported) .bari-push-off-item,
    html.bari-push-subscribed .bari-push-on-item,
    html:not(.bari-push-subscribed) .bari-push-off-item { display: none !important; }
</style>
<script>
    (() => {
        const bari = (window.bari = window.bari || {});

        if (bari.push) return;

        const text = @js([
            'on' => __('Notifications are on for this device.'),
            'off' => __('Notifications are off for this device.'),
            'blocked' => __('Notifications are blocked for Bari. Allow them in your phone or browser settings, then try again.'),
            'failed' => __('Notifications could not be turned on. Try again in a moment.'),
        ]);

        const supported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const tell = (message, status = 'success') => {
            if (window.FilamentNotification) {
                new window.FilamentNotification().title(message).status(status).send();
            } else {
                window.alert(message);
            }
        };
        const keyBytes = (base64) => {
            const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
            return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
        };
        const send = (method, body) => fetch('/push-subscriptions', {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(body),
        });

        const refresh = async () => {
            const root = document.documentElement;
            if (! supported()) return;
            root.classList.add('bari-push-supported');
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            root.classList.toggle('bari-push-subscribed', Boolean(subscription) && Notification.permission === 'granted');
        };

        bari.push = {
            async enable() {
                if (Notification.permission === 'denied') return tell(text.blocked, 'warning');

                try {
                    if (await Notification.requestPermission() !== 'granted') return tell(text.blocked, 'warning');

                    const registration = await navigator.serviceWorker.ready;
                    const key = document.querySelector('meta[name="vapid-public-key"]').content;
                    const subscription = await registration.pushManager.getSubscription()
                        ?? await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(key) });
                    const response = await send('POST', subscription.toJSON());

                    if (! response.ok) throw new Error(response.status);
                    tell(text.on);
                } catch (error) {
                    tell(text.failed, 'danger');
                }

                refresh();
            },

            async disable() {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();

                if (subscription) {
                    await send('DELETE', { endpoint: subscription.endpoint }).catch(() => {});
                    await subscription.unsubscribe().catch(() => {});
                }

                tell(text.off);
                refresh();
            },
        };

        refresh().catch(() => {});
        document.addEventListener('livewire:navigated', () => refresh().catch(() => {}));
    })();
</script>
