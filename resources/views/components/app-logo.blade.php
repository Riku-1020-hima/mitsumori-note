@props(['size' => 'w-7 h-7'])

{{-- ミツモリノートのマーク(書類と円) --}}
<span {{ $attributes->merge(['class' => "$size shrink-0 inline-flex rounded-lg bg-brand-500 text-white items-center justify-center"]) }} aria-hidden="true">
    <svg viewBox="0 0 24 24" class="w-[62%] h-[62%]" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 3h8.5L19 7.5V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5"/><path d="M9.5 11l2.5 3 2.5-3M12 14v4.5M10 15.5h4M10 17.5h4"/>
    </svg>
</span>
