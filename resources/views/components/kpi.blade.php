@props(['title' => '', 'value' => '', 'delta' => '', 'icon' => '📊'])

<div class="bg-white rounded-2xl shadow p-5">
    <div class="flex items-start justify-between">
        <div>
            <p class="text-sm text-gray-500">{{ $title }}</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $value }}</p>
            @if($delta !== '')
                <p class="mt-1 text-xs {{ Str::startsWith($delta, '-') ? 'text-red-600' : 'text-emerald-600' }}">{{ $delta }}</p>
            @endif
        </div>
        <div class="text-2xl">{{ $icon }}</div>
    </div>
</div>
