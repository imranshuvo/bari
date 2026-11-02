{{-- iPhone/iPad Safari has no install button: a one-time tip until dismissed. --}}
<div
    x-data="{
        show: false,
        init() {
            const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            let dismissed = false;
            try { dismissed = localStorage.getItem('bari-ios-tip-dismissed') === '1'; } catch (e) {}
            this.show = ios && ! window.bari?.isStandalone?.() && ! dismissed;
        },
        dismiss() {
            this.show = false;
            try { localStorage.setItem('bari-ios-tip-dismissed', '1'); } catch (e) {}
        },
    }"
    x-show="show"
    x-cloak
    class="hcp-ios-tip"
>
    <img src="/icons/icon-192.png" alt="" class="hcp-ios-tip-icon">
    <p class="hcp-ios-tip-text">
        {{ __('Add Bari to your home screen: tap Share, then Add to Home Screen.') }}
    </p>
    <button type="button" x-on:click="dismiss()" class="hcp-ios-tip-close" aria-label="{{ __('Close') }}">
        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
    </button>
</div>
