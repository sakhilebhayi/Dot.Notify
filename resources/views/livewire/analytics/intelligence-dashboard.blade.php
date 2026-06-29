<div class="bg-white rounded-xl shadow p-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Universal Intelligence Query</h3>
    <p class="text-sm text-gray-500 mb-4">
        Ask any cross-platform question. Dot.Analytics traces relationships across all connected platforms to answer it.
    </p>

    <form wire:submit="askIntelligence" class="flex gap-3">
        <input
            type="text"
            wire:model="intelligenceQuery"
            placeholder="Why is productivity down this month?"
            class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
        />
        <button
            type="submit"
            class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove>Ask</span>
            <span wire:loading>Analysing...</span>
        </button>
    </form>

    @error('intelligenceQuery')
        <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
    @enderror

    @if($queryAnswer)
        <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-gray-700 leading-relaxed">
            {{ $queryAnswer }}
        </div>
    @endif
</div>
