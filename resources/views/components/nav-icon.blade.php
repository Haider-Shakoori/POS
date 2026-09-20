@props(['name'])

<svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('dashboard') <path d="M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6Zm10-12h8V3h-8v6Z"/> @break
        @case('pos') <path d="M4 5h16v11H4z"/><path d="M7 9h4M7 12h2M15 9h2M15 12h2M8 20h8M12 16v4"/> @break
        @case('sales') <path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/> @break
        @case('customers') <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/> @break
        @case('products') <path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/> @break
        @case('inventory') <path d="M4 4h16v16H4z"/><path d="M4 9h16M9 4v16"/> @break
        @case('purchase') <path d="M3 6h2l2.2 9.2a2 2 0 0 0 2 1.5h7.6a2 2 0 0 0 2-1.6L20 9H7"/><circle cx="10" cy="20" r="1"/><circle cx="17" cy="20" r="1"/> @break
        @case('receipt') <path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2Z"/><path d="M9 7h6M9 11h6M9 15h4"/> @break
        @case('supplier') <path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6M9 9h.01M15 9h.01"/> @break
        @case('expenses') <path d="M12 2v20M17 5H9.5a3.5 3.5 0 1 0 0 7H14a3.5 3.5 0 1 1 0 7H6"/> @break
        @case('cash') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h.01M17 15h.01"/><circle cx="12" cy="12" r="2"/> @break
        @case('closing') <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/> @break
        @case('reports') <path d="M4 19V9M10 19V5M16 19v-7M22 19V3"/><path d="M2 21h22"/> @break
        @case('users') <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/> @break
        @case('roles') <path d="M12 3 4 6v6c0 5 3.4 8.6 8 9 4.6-.4 8-4 8-9V6l-8-3Z"/><path d="M9 12h6M12 9v6"/> @break
        @case('audit') <path d="M12 3 4 6v6c0 5 3.4 8.6 8 9 4.6-.4 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/> @break
        @case('terminal') <rect x="3" y="3" width="18" height="14" rx="2"/><path d="M8 21h8M12 17v4"/> @break
        @case('settings') <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.09A1.7 1.7 0 0 0 9 19.35a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.63 15 1.7 1.7 0 0 0 3.09 14H3v-4h.09A1.7 1.7 0 0 0 4.65 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.63 1.7 1.7 0 0 0 10 3.09V3h4v.09A1.7 1.7 0 0 0 15 4.65a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.37 9 1.7 1.7 0 0 0 20.91 10H21v4h-.09A1.7 1.7 0 0 0 19.4 15Z"/> @break
        @default <circle cx="12" cy="12" r="9"/>
    @endswitch
</svg>
