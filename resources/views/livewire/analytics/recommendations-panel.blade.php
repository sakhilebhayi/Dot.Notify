<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-800">AI Recommendations</h3>
        <button
            wire:click="generate"
            class="text-xs px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            wire:loading.attr="disabled"
            wire:target="generate"
        >
            <span wire:loading.remove wire:target="generate">Generate</span>
            <span wire:loading wire:target="generate">Analysing...</span>
        </button>
    </div>

    @if($this->recommendations->isEmpty())
        <p class="text-sm text-gray-400 py-4 text-center">No pending recommendations. Click Generate to run the intelligence engines.</p>
    @else
        <ul class="space-y-3">
            @foreach($this->recommendations as $rec)
                <li class="border border-gray-200 rounded-lg p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full @if($rec->priority === 'critical') bg-red-100 text-red-700 @elseif($rec->priority === 'high') bg-amber-100 text-amber-700 @else bg-gray-100 text-gray-600 @endif">
                                    {{ ucfirst($rec->priority) }}
                                </span>
                                <span class="text-xs text-gray-400">{{ ucfirst(str_replace('_', ' ', $rec->engine)) }} Engine</span>
                            </div>
                            <p class="text-sm font-medium text-gray-800">{{ $rec->title }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $rec->rationale }}</p>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <button wire:click="action({{ $rec->id }})" class="text-xs px-2 py-1 bg-blue-100 hover:bg-blue-200 rounded text-blue-700">Act</button>
                            <button wire:click="dismiss({{ $rec->id }})" class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Dismiss</button>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
