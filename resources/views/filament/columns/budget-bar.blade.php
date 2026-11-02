@php
    $row = $getState();
    $scale = max($row['budget'], $row['expected'], 1);
    $over = $row['left'] < 0;
@endphp
<div class="w-28 px-3 py-2 lg:w-40" title="{{ $row['used_percent'] }}%">
    <div class="flex h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
        <div class="{{ $over ? 'bg-danger-600' : 'bg-primary-600' }}" style="width: {{ $row['paid'] / $scale * 100 }}%"></div>
        <div class="{{ $over ? 'bg-danger-300 dark:bg-danger-800' : 'bg-primary-300 dark:bg-primary-700' }}" style="width: {{ ($row['billed'] - $row['paid']) / $scale * 100 }}%"></div>
        <div class="bg-gray-300 dark:bg-white/20" style="width: {{ max(0, $row['expected'] - $row['billed']) / $scale * 100 }}%"></div>
    </div>
    @if ($row['budget'] > 0)
        <div class="money mt-1 text-right text-xs {{ $over ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $row['used_percent'] }}%</div>
    @endif
</div>
