@props(['name' => 'home'])
<svg {{ $attributes->class(['sm-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
@case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>@break
@case('pin')<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>@break
@case('building')<path d="M4 21V3h12v18M16 9h4v12M2 21h20M8 7h4M8 11h4M8 15h4M9 21v-3h3"/>@break
@case('grid')<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>@break
@case('wallet')<path d="M20 8H5a2 2 0 0 1 0-4h13v4M3 6v13a2 2 0 0 0 2 2h15V8M20 12h-5v5h5"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-14 4h3m4 0h3"/>@break
@case('tool')<path d="m14 6 4 4 3-3a7 7 0 0 1-9 9l-6 6-4-4 6-6a7 7 0 0 1 9-9Z"/>@break
@case('user')<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>@break
@case('mail')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>@break
@case('star')<path d="m12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1Z"/>@break
@case('arrow')<path d="M4 12h16m-6-6 6 6-6 6"/>@break
@case('filter')<path d="M3 6h18M3 12h18M3 18h18"/><circle cx="8" cy="6" r="2" fill="currentColor"/><circle cx="16" cy="12" r="2" fill="currentColor"/><circle cx="9" cy="18" r="2" fill="currentColor"/>@break
@case('check')<path d="m5 12 4 4L19 6"/>@break
@default<path d="m3 11 9-8 9 8v10h-7v-7h-4v7H3Z"/>
@endswitch
</svg>
