@props(['name'])
{{-- The shell's own icons: one 24px grid, one stroke weight, drawn inline so no icon font is fetched. --}}
<svg {{ $attributes->class('icon') }} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
@switch($name)
    @case('home')<path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9h12v-9"/><path d="M10 19v-5h4v5"/>@break
    @case('apps')<circle cx="12" cy="12" r="2.5"/><circle cx="5" cy="6" r="2"/><circle cx="19" cy="6" r="2"/><circle cx="12" cy="20" r="1.5"/><path d="m6.7 7.3 3.4 3M17.3 7.3l-3.4 3M12 14.5v4"/>@break
    @case('user')<circle cx="12" cy="8" r="4"/><path d="M4.5 20c1-3.6 4-5.5 7.5-5.5s6.5 1.9 7.5 5.5"/>@break
    @case('users')<circle cx="9" cy="8" r="3.5"/><path d="M2.5 19.5c.8-3.2 3.4-5 6.5-5s5.7 1.8 6.5 5"/><path d="M16 4.8a3.5 3.5 0 0 1 0 6.4M18.5 14.9c1.6.8 2.7 2.4 3 4.6"/>@break
    @case('user-plus')<circle cx="10" cy="8" r="3.5"/><path d="M3.5 19.5c.8-3.2 3.4-5 6.5-5 1.7 0 3.3.5 4.5 1.5"/><path d="M19 13v6M16 16h6"/>@break
    @case('logout')<path d="M14 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 8l-4 4 4 4M6 12h9"/>@break
    @case('bell')<path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2H4.5z"/><path d="M10 20.5a2.2 2.2 0 0 0 4 0"/>@break
    @case('moon')<path d="M19.5 14.5A8 8 0 0 1 9.5 4.5a8 8 0 1 0 10 10z"/>@break
    @case('sun')<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2.5M12 19v2.5M2.5 12H5M19 12h2.5M5.3 5.3 7 7M17 17l1.7 1.7M18.7 5.3 17 7M7 17l-1.7 1.7"/>@break
    @case('menu')<path d="M4 7h16M4 12h16M4 17h16"/>@break
    @case('close')<path d="M6 6l12 12M18 6 6 18"/>@break
    @case('search')<circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/>@break
    @case('check')<path d="m5 12.5 4.5 4.5L19 7.5"/>@break
    @case('email')<rect x="3.5" y="5.5" width="17" height="13" rx="1.5"/><path d="m4.5 7.5 7.5 6 7.5-6"/>@break
    @case('sms')<path d="M5 5.5h14a1.5 1.5 0 0 1 1.5 1.5v8a1.5 1.5 0 0 1-1.5 1.5h-7l-4.5 3.5v-3.5H5A1.5 1.5 0 0 1 3.5 15V7A1.5 1.5 0 0 1 5 5.5z"/>@break
    @case('push')<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 17.5h2"/>@break
    @case('webhook')<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>@break
    @case('slack')<path d="M9.5 4 8 20M16 4l-1.5 16M4.5 9h16M3.5 15h16"/>@break
    @case('draft')<path d="M5 19l1-4L16.5 4.5a2 2 0 0 1 3 3L9 18z"/><path d="M14.5 6.5l3 3"/>@break
    @default<rect x="4" y="5" width="16" height="12" rx="1.5"/><path d="M9 20.5h6M12 17v3.5"/>
@endswitch
</svg>
