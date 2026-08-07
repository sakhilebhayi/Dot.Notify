<div class="relative min-h-screen flex flex-col justify-center items-center px-5 py-12 overflow-hidden" style="background: radial-gradient(ellipse 80% 60% at 15% 0%, rgba(139,195,74,0.10) 0%, transparent 60%), var(--ink);">
    {{-- Same signature element as welcome.blade.php's hero — line-art bell + ping arcs, echoing
    the logo's own bell icon and the "signal" every notification is. --}}
    <svg class="hidden lg:block absolute right-[6%] bottom-[10%] h-[50%] w-auto opacity-[0.13] pointer-events-none" viewBox="0 0 240 300" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M120 40C90 40 70 65 70 100V150L50 190H190L170 150V100C170 65 150 40 120 40Z" stroke="#eef1ea" stroke-width="3" stroke-linejoin="round"/>
        <path d="M95 190C95 205 106 215 120 215C134 215 145 205 145 190" stroke="#eef1ea" stroke-width="3"/>
        <circle cx="120" cy="40" r="10" stroke="#eef1ea" stroke-width="3"/>
        <path d="M172 58C182 58 190 66 190 76" stroke="#f0c33a" stroke-width="3" stroke-linecap="round"/>
        <path d="M172 40C192 40 208 56 208 76" stroke="#f0c33a" stroke-width="3" stroke-linecap="round" opacity="0.6"/>
        <path d="M172 22C202 22 226 46 226 76" stroke="#f0c33a" stroke-width="3" stroke-linecap="round" opacity="0.35"/>
    </svg>

    <div class="relative z-10 mb-8">
        {{ $logo }}
    </div>

    <div class="relative z-10 w-full sm:max-w-md px-6 py-8 sm:px-8 bg-[var(--ink-soft)] border border-[var(--line)] rounded-2xl shadow-xl">
        {{ $slot }}
    </div>
</div>
