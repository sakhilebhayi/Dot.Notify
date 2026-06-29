<div class="dot-card" style="padding:1.5rem;">
    <div class="flex items-center justify-between mb-4">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;">Connected Platforms</h3>
        <button wire:click="$toggle('showForm')" class="text-xs px-3 py-1.5 bg-gray-800 text-white rounded hover:bg-gray-700">
            {{ $showForm ? 'Cancel' : '+ Add Source' }}
        </button>
    </div>

    @if($showForm)
        <form wire:submit="addSource" class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Platform key</label>
                <input wire:model="platform" type="text" placeholder="dot.finance" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                @error('platform') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Display name</label>
                <input wire:model="displayName" type="text" placeholder="Dot.Finance" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                @error('displayName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Base URL (optional)</label>
                <input wire:model="baseUrl" type="url" placeholder="https://finance.infodot.app" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                @error('baseUrl') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-3">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Add Platform</button>
            </div>
        </form>
    @endif

    @if($this->sources->isEmpty())
        <p class="text-sm text-gray-400 py-4 text-center">No platforms connected. Add a source to start ingesting intelligence.</p>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($this->sources as $source)
                <div class="border border-gray-200 rounded-lg p-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $source->display_name }}</p>
                        <p class="text-xs text-gray-400">{{ $source->platform }}
                            @if($source->last_synced_at) · Last sync {{ $source->last_synced_at->diffForHumans() }} @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $source->status === 'connected' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ ucfirst($source->status) }}
                        </span>
                        <button wire:click="toggleStatus({{ $source->id }})" class="text-xs text-gray-400 hover:text-gray-600">Toggle</button>
                        <button wire:click="deleteSource({{ $source->id }})" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
