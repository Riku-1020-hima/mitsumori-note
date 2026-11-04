@props(['doc'])

{{-- 状態の札。色だけに頼らず、文字でも分かるようにする --}}
@php
    $style = match (true) {
        $doc->isOverdue() => 'bg-alert-50 text-alert-600 border-alert-500/40',
        $doc->isVoid() => 'bg-stone-100 text-stone-500 border-stone-300 line-through',
        in_array($doc->status, ['won', 'paid'], true) => 'bg-brand-50 text-brand-700 border-brand-100',
        $doc->status === 'sent' => 'bg-amber-50 text-amber-900 border-amber-200',
        $doc->status === 'unpaid' => 'bg-white text-stone-700 border-stone-300',
        default => 'bg-stone-100 text-stone-600 border-stone-200',
    };
@endphp
<span class="inline-block whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-bold {{ $style }}">
    {{ $doc->isOverdue() ? '期限切れ' : $doc->status_label }}
</span>
