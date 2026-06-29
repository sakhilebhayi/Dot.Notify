<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Intelligence Alerts</h3>
        <div class="flex gap-2">
            <select wire:model.live="filterSeverity" class="border border-gray-300 rounded text-xs px-2 py-1">
                <option value="">All severities</option>
                <option value="critical">Critical</option>
                <option value="warning">Warning</option>
                <option value="info">Info</option>
            </select>
            <select wire:model.live="filterStatus" class="border border-gray-300 rounded text-xs px-2 py-1">
                <option value="open">Open</option>
                <option value="acknowledged">Acknowledged</option>
                <option value="resolved">Resolved</option>
                <option value="">All</option>
            </select>
        </div>
    </div>

    @if($this->alerts->isEmpty())
        <p class="text-sm text-gray-400 py-4 text-center">No alerts for the selected filters.</p>
    @else
        <ul class="space-y-3">
            @foreach($this->alerts as $alert)
                <li class="border rounded-lg p-3 @if($alert->severity === 'critical') border-red-300 bg-red-50 @elseif($alert->severity === 'warning') border-amber-300 bg-amber-50 @else border-gray-200 @endif">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $alert->title }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $alert->description }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $alert->triggered_at->diffForHumans() }}</p>
                        </div>
                        @if($alert->isOpen())
                            <div class="flex gap-1 shrink-0">
                                <button wire:click="acknowledge({{ $alert->id }})" class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Ack</button>
                                <button wire:click="resolve({{ $alert->id }})" class="text-xs px-2 py-1 bg-green-100 hover:bg-green-200 rounded text-green-700">Resolve</button>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
