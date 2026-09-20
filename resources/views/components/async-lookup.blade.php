@props([
    'name',
    'label',
    'endpoint',
    'selectedValue' => '',
    'selectedLabel' => '',
    'selectedMeta' => '',
    'placeholder' => null,
])

<div
    class="relative"
    x-data="asyncLookup({
        endpoint: @js($endpoint),
        initialValue: @js((string) $selectedValue),
        initialLabel: @js((string) $selectedLabel),
        initialMeta: @js((string) $selectedMeta),
    })"
    @click.outside="close()"
>
    <label class="field-label">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" x-model="value">

    <div class="relative">
        <input
            class="field pe-10"
            type="search"
            x-model="query"
            @focus="focus()"
            @input.debounce.250ms="handleInput()"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="chooseActive()"
            @keydown.escape="close()"
            placeholder="{{ $placeholder ?: __('ui.search_to_select') }}"
            autocomplete="off"
        >

        <div class="pointer-events-none absolute inset-y-0 end-3 flex items-center">
            <svg x-show="loading" class="size-4 animate-spin text-slate-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
                <path class="opacity-75" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
            </svg>
            <button
                x-show="!loading && value"
                type="button"
                class="pointer-events-auto text-lg leading-none text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                @click="clear()"
                aria-label="{{ __('ui.clear_selection') }}"
            >×</button>
        </div>
    </div>

    <div x-show="selectedMeta && value" x-text="selectedMeta" class="mt-1 text-[11px] text-slate-400"></div>

    <div
        x-cloak
        x-show="open"
        class="absolute z-50 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-950/10 dark:border-slate-700 dark:bg-slate-900"
    >
        <template x-if="!loading && results.length === 0">
            <div class="px-3 py-3 text-sm text-slate-400" x-text="query.trim().length < 2 ? @js(__('ui.type_two_characters')) : @js(__('ui.no_matches_found'))"></div>
        </template>

        <template x-for="(item, index) in results" :key="item.id">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-4 rounded-xl px-3 py-2.5 text-start text-sm transition"
                :class="index === activeIndex ? 'bg-brand-50 text-brand-800 dark:bg-brand-950/50 dark:text-brand-200' : 'hover:bg-slate-50 dark:hover:bg-slate-800'"
                @mouseenter="activeIndex = index"
                @click="select(item)"
            >
                <span class="min-w-0">
                    <span class="block truncate font-semibold" x-text="item.label"></span>
                    <span x-show="item.meta" class="mt-0.5 block truncate text-xs text-slate-400" x-text="item.meta"></span>
                </span>
                <span class="shrink-0 text-xs font-bold text-brand-600 dark:text-brand-300">{{ __('ui.select') }}</span>
            </button>
        </template>
    </div>
</div>
