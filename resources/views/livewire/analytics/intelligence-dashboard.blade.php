<div class="dot-card" style="padding:1.5rem;">
    <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0 0 1rem;">Universal Intelligence Query</h3>
    <p style="font-size:0.8rem;color:#71717a;margin:0 0 1rem;">
        Ask any cross-platform question. Dot.Analytics traces relationships across all connected platforms to answer it.
    </p>

    <form wire:submit="askIntelligence" class="flex gap-3">
        <input
            type="text"
            wire:model="intelligenceQuery"
            placeholder="Why is productivity down this month?"
            class="dot-input" style="flex:1;"
        />
        <button
            type="submit"
            class="dot-btn dot-btn-primary"
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
        <div class="dot-card" style="margin-top:1rem;padding:1rem;border-color:rgba(var(--accent-rgb),0.2);background:rgba(var(--accent-rgb),0.05);">
            {{ $queryAnswer }}
        </div>
    @endif
</div>
