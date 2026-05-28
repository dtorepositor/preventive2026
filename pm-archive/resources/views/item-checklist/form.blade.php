@extends('layouts.app')

@section('title', $isEdit ? 'Edit Item Checklist' : 'New Item Checklist')

@section('content')
<style>
    .checklist-form {
        max-width: 210mm;
        margin: 0 auto;
        background: white;
        padding: 2rem;
        color: black;
        font-family: Arial, sans-serif;
    }
    .checklist-table {
        width: 100%;
        border-collapse: collapse;
        border: 2px solid black;
    }
    .checklist-table th, .checklist-table td {
        border: 1px solid black;
        padding: 4px 8px;
        font-size: 13px;
        vertical-align: top;
    }
    .checklist-table th {
        font-weight: bold;
        text-transform: uppercase;
        background-color: #f3f4f6;
        text-align: center;
        vertical-align: middle;
    }
    .radio-cell {
        text-align: center;
        vertical-align: middle !important;
    }
    .radio-input {
        accent-color: black;
        width: 16px;
        height: 16px;
    }
    .header-section {
        border-bottom: 2px solid black;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
</style>

<form method="POST" action="{{ $isEdit ? route('item-checklist.update', $itemChecklist) : route('item-checklist.store', $preventiveMaintenance) }}" class="checklist-form" id="itemChecklistForm">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="header-section">
        <h2 class="text-xl font-bold uppercase tracking-wide">ITEM CHECKLIST</h2>
    </div>

    {{-- Maintenance Date Field --}}
    <div class="mb-4">
        <label class="font-bold text-sm block">Maintenance Date</label>
        <input
            type="date"
            @if ($isEdit) name="maintenance_date" @endif
            value="{{ $isEdit ? old('maintenance_date', $valueMap['maintenance_date'] ?? $itemChecklist?->getValueByVarName('maintenance_date')) : now(config('app.timezone'))->toDateString() }}"
            @disabled(! $isEdit)
            class="border border-black px-2 py-1 text-sm mt-1 disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
        >
    </div>

    <table class="checklist-table">
        <thead>
            <tr>
                <th style="width: 50px;">ITEM #</th>
                <th style="width: 200px;">TASK</th>
                <th>DESCRIPTION</th>
                <th style="width: 50px;">OK</th>
                <th style="width: 60px;">REPAIR</th>
                <th style="width: 50px;">N/A</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grouped = collect($entries)->groupBy('item_no');
            @endphp

            @foreach ($grouped as $itemNo => $group)
                @foreach ($group as $gIndex => $entry)
                    @php
                        $index = collect($entries)->search(fn ($e) =>
                            $e['item_no'] === $entry['item_no'] &&
                            $e['description'] === $entry['description'] &&
                            $e['sort_order'] === $entry['sort_order']
                        );
                    @endphp
                    <tr>
                        @if ($gIndex === 0)
                            <td rowspan="{{ $group->count() }}" class="text-center font-bold">{{ $itemNo }}</td>
                            <td rowspan="{{ $group->count() }}" class="font-bold">{{ $entry['task'] }}</td>
                        @endif
                        <td>{{ $entry['description'] }}</td>
                        <td class="radio-cell">
                            <input type="radio" name="entries[{{ $index }}][status]" value="ok" {{ ($entry['status'] ?? '') === 'ok' ? 'checked' : '' }} class="radio-input">
                        </td>
                        <td class="radio-cell">
                            <input type="radio" name="entries[{{ $index }}][status]" value="repair" {{ ($entry['status'] ?? '') === 'repair' ? 'checked' : '' }} class="radio-input">
                        </td>
                        <td class="radio-cell">
                            <input type="radio" name="entries[{{ $index }}][status]" value="na" {{ ($entry['status'] ?? '') === 'na' ? 'checked' : '' }} class="radio-input">
                        </td>
                    </tr>
                @endforeach
            @endforeach
            
            {{-- Summary/Recommendation Row --}}
            <tr>
                <td colspan="2" class="font-bold p-3 align-middle bg-gray-50">Summary/Recommendation</td>
                <td colspan="4" class="p-0">
                    <textarea name="summary_recommendation" rows="4" class="w-full h-full border-0 focus:ring-0 p-2 text-sm resize-none" placeholder="">{{ old('summary_recommendation', $valueMap['summary_recommendation'] ?? $itemChecklist->summary_recommendation ?? '') }}</textarea>
                </td>
            </tr>
        </tbody>
    </table>

    <p class="text-xs italic mt-2">Note: To be filled by Technician attending to ICT Equipment.</p>

    {{-- Signatures --}}
    <div class="flex justify-between mt-12 gap-8">
        <div class="flex-1">
            <p class="mb-8 font-bold text-sm">Technical Staff</p>
            <input type="text" name="checked_by" value="{{ old('checked_by', $valueMap['checked_by'] ?? $itemChecklist->checked_by ?? '') }}" class="w-full border-0 border-b border-black focus:ring-0 px-0 py-1 text-center font-bold">
        </div>
        <div class="flex-1">
            <p class="mb-8 font-bold text-sm">Director</p>
            <input type="text" name="conforme_by" value="{{ old('conforme_by', $valueMap['conforme_by'] ?? $itemChecklist->conforme_by ?? '') }}" class="w-full border-0 border-b border-black focus:ring-0 px-0 py-1 text-center font-bold">
        </div>
    </div>

    <div class="mt-8 flex gap-3 print:hidden">
         <button
            type="submit"
            class="px-6 py-2 bg-black text-white font-bold text-sm hover:bg-gray-800 uppercase"
            data-loading-text="{{ $isEdit ? 'Updating...' : 'Saving...' }}"
        >
            {{ $isEdit ? 'Update' : 'Save' }}
        </button>
        <a href="{{ route('preventive-maintenance.show', $preventiveMaintenance) }}" class="px-6 py-2 border border-black text-black font-bold text-sm hover:bg-gray-100 uppercase">Cancel</a>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('itemChecklistForm');
    if (!form) return;

    const submitBtn = form.querySelector('button[type="submit"]');
    if (!submitBtn) return;

    form.addEventListener('submit', function () {
        submitBtn.disabled = true;
        const loadingText = submitBtn.dataset.loadingText || 'Saving...';
        submitBtn.dataset.originalText = submitBtn.textContent;
        submitBtn.textContent = loadingText;

        Swal.fire({
            title: loadingText,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => { Swal.showLoading(); }
        });
    });
});
</script>
@endpush

@endsection
