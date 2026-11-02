<div class="mt-6 flex justify-center gap-4 text-sm">
    @foreach ($locales as $code => $label)
        @if (app()->getLocale() === $code)
            <span class="font-semibold text-gray-950 dark:text-white">{{ $label }}</span>
        @else
            <a href="{{ route('locale', $code) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $label }}</a>
        @endif
    @endforeach
</div>
