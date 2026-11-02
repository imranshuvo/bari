<aside class="hcp-auth-aside" aria-hidden="true">
    <div class="hcp-auth-aside-glow"></div>

    <div class="relative flex h-full flex-col justify-between p-10 xl:p-14">
        <div class="hcp-brand hcp-brand-invert">
            <span class="hcp-brand-mark">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.5 11.2 12 4.3l8.5 6.9v8.3a1.2 1.2 0 0 1-1.2 1.2h-4.6v-5.4H9.3v5.4H4.7a1.2 1.2 0 0 1-1.2-1.2z"/></svg>
            </span>
            <span class="hcp-brand-name">Bari</span>
        </div>

        <div class="max-w-lg">
            <h2 class="text-4xl font-semibold leading-tight tracking-tight text-white xl:text-5xl">
                {{ __('Build your home without money surprises.') }}
            </h2>
            <ul class="mt-8 space-y-4 text-base text-emerald-50/90">
                @foreach ([
                    __('See what you have spent – and what is still coming'),
                    __('Snap a photo of every bill and receipt'),
                    __('Share with your partner, builder or bank'),
                ] as $point)
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="size-3.5 text-white"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4l3.3 3.3 7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                        </span>
                        {{ $point }}
                    </li>
                @endforeach
            </ul>

            {{-- Illustration of the dashboard, not live data. --}}
            <div class="mt-12 max-w-sm rounded-2xl bg-white/10 p-5 ring-1 ring-white/20 backdrop-blur-md">
                <div class="flex items-center justify-between text-sm text-emerald-50/80">
                    <span>{{ __('Left in budget') }}</span>
                    <span class="rounded-full bg-white/15 px-2 py-0.5 text-xs font-medium text-white">49%</span>
                </div>
                <div class="mt-1 text-3xl font-semibold tracking-tight text-white">৳60,92,500</div>
                <div class="mt-4 flex h-2 gap-0.5 overflow-hidden rounded-full bg-white/15">
                    <div class="w-[37%] rounded-full bg-white"></div>
                    <div class="w-[12%] rounded-full bg-white/60"></div>
                </div>
            </div>
        </div>

        <p class="text-sm text-emerald-50/60">© {{ date('Y') }} Bari</p>
    </div>
</aside>
