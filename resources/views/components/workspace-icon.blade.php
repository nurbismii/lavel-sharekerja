@props(['name' => 'grid'])
@php($paths = [
    'grid' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
    'building' => 'M4 21V5h11v16 M15 10h5v11 M8 9h3 M8 13h3 M8 17h3 M2 21h20',
    'briefcase' => 'M9 7V4h6v3 M3 7h18v13H3z M3 12l9 3 9-3 M12 12v5',
    'credit' => 'M3 5h18v14H3z M3 10h18 M7 15h3',
    'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M17 4a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-3.87',
    'flag' => 'M4 22V3 M4 4c5-4 11 4 16 0v10c-5 4-11-4-16 0',
    'mail' => 'M3 5h18v14H3z M3 6l9 7 9-7',
    'clock' => 'M12 8v5l3 2 M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0',
    'eye' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12 M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
    'arrow' => 'M7 17 17 7 M7 7h10v10',
])
<svg {{ $attributes->class(['workspace-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['grid'] }}" /></svg>
