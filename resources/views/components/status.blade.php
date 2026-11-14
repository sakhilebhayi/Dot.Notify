@props(['kind' => 'off', 'label'])
{{-- A state said in words with a mark, so it never rests on colour alone.
     kind: sent | on (tick), failed (cross), retrying | waiting (clock), anything else (dash). --}}
<span {{ $attributes->class(['nf-status', 'nf-status-'.$kind]) }}>
    <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">
        @switch($kind)
            @case('sent')
            @case('on')<path d="m3 8.5 3.2 3.2L13 4.8"/>@break
            @case('failed')<path d="m4 4 8 8M12 4l-8 8"/>@break
            @case('retrying')
            @case('waiting')<circle cx="8" cy="8" r="5.5"/><path d="M8 5v3.2l2 1.3"/>@break
            @default<path d="M4 8h8"/>
        @endswitch
    </svg>
    {{ $label }}
</span>
