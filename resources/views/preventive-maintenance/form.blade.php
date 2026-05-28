@extends('layouts.app')

@section('title', $isEdit ? 'Edit Checklist' : 'Preventive Maintenance Checklist')

@section('content')
<form method="POST" action="{{ $isEdit ? route('preventive-maintenance.update', $submission) : route('preventive-maintenance.store') }}" class="bg-white text-black max-w-4xl mx-auto shadow-sm border-[1.5px] border-black p-4" id="preventiveMaintenanceForm">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    {{-- Document header: logo + institution + title + date --}}
    <div class="border-b border-black pb-3">
        <div class="flex items-start gap-4">
            <div class="shrink-0 w-16 h-16 rounded-full border-4 border-emerald-600 flex items-center justify-center bg-amber-50 text-amber-800 font-bold text-sm">
                CMU
            </div>
            <div class="flex-1">
                <p class="text-sm tracking-wide">Republic of the Philippines</p>
                <p class="text-base font-bold tracking-wide">CENTRAL MINDANAO UNIVERSITY</p>
                <p class="text-sm">University Town, Musuan, Bukidnon</p>
            </div>
        </div>
        <p class="text-center font-semibold tracking-wide mt-2">OFFICE OF DIGITAL TRANSFORMATION</p>
        <div class="flex items-baseline justify-center gap-4 mt-2">
            <h1 class="text-xl font-bold tracking-wide">PREVENTIVE MAINTENANCE CHECKLIST</h1>
            <div class="flex items-center gap-2">
                <span class="text-sm">Date:</span>
                <input
                    type="date"
                    @if ($isEdit) name="checklist_date" @endif
                    value="{{ $isEdit ? old('checklist_date', $checklist->checklist_date?->format('Y-m-d')) : now(config('app.timezone'))->toDateString() }}"
                    @disabled(! $isEdit)
                    class="border-0 border-b-2 border-black bg-transparent py-0 text-sm w-36 focus:ring-0 focus:border-black disabled:text-slate-500 disabled:cursor-not-allowed"
                >
            </div>
        </div>
    </div>

    {{-- User/PC Information — boxed list at top --}}
    <div class="border-2 border-black mt-3">
        <div class="border-b border-black flex">
            <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">User/Operator:</span>
            <input type="text" name="user_operator" value="{{ old('user_operator', $checklist->user_operator) }}" placeholder="" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
        </div>
        <div class="border-b border-black flex">
            <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Office/College:</span>
            <input type="text" name="office_college" value="{{ old('office_college', $checklist->office_college) }}" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
        </div>
        <div class="border-b border-black flex">
            <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Department:</span>
            <input type="text" name="department" value="{{ old('department', $checklist->department) }}" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
        </div>
        <div class="border-b border-black flex">
            <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Date Acquired:</span>
            <input
                type="date"
                @if ($isEdit) name="date_acquired" @endif
                value="{{ $isEdit ? old('date_acquired', $checklist->date_acquired?->format('Y-m-d')) : now(config('app.timezone'))->toDateString() }}"
                @disabled(! $isEdit)
                class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none disabled:text-slate-500 disabled:cursor-not-allowed"
            >
        </div>
        <div class="flex">
            <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">PC Name:</span>
            <input type="text" name="pc_name" value="{{ old('pc_name', $checklist->pc_name) }}" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
        </div>
    </div>

    {{-- Equipment Installed --}}
    <div class="mt-3">
        <h2 class="text-sm font-bold mb-1">Equipment Installed</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-1">
            @foreach ($equipment as $item)
            <label class="flex items-center gap-2 cursor-pointer text-sm">
                <input type="checkbox" name="equipment_{{ strtolower(str_replace(' ', '_', $item->name)) }}" value="1" {{ old('equipment_' . strtolower(str_replace(' ', '_', $item->name)), $checklist->{'equipment_' . strtolower(str_replace(' ', '_', $item->name))} ?? false) ? 'checked' : '' }} class="rounded border-black text-emerald-600">
                <span>{{ $item->name }}</span>
            </label>
            @endforeach
            <label class="flex items-center gap-2 cursor-pointer text-sm col-span-2 sm:col-span-1">
                <input type="checkbox" name="equipment_others" value="1" {{ old('equipment_others', $checklist->equipment_others) ? 'checked' : '' }} id="equipment_others" class="rounded border-black text-emerald-600">
                <span>Others(Specify)</span>
            </label>
            <span id="equipment_others_wrap" class="{{ old('equipment_others', $checklist->equipment_others) ? '' : 'hidden' }} col-span-2 sm:col-span-1 flex items-center">
                <input type="text" name="equipment_others_specify" value="{{ old('equipment_others_specify', $checklist->equipment_others_specify) }}" class="border-0 border-b border-black bg-transparent py-0 text-sm w-full focus:ring-0 focus:border-black">
            </span>
        </div>
    </div>

    {{-- Operating System Installed --}}
    <div class="mt-3">
        <h2 class="text-sm font-bold mb-1">Operating System Installed</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-1">
            @foreach ($operatingSystems as $item)
            @php $fieldName = 'os_' . strtolower(str_replace([' ', '.', '-'], '_', $item->name)); @endphp
            <label class="flex items-center gap-2 cursor-pointer text-sm">
                <input type="checkbox" name="{{ $fieldName }}" value="1" {{ old($fieldName, $checklist->$fieldName ?? false) ? 'checked' : '' }} class="rounded border-black text-emerald-600">
                <span>{{ $item->name }}</span>
            </label>
            @endforeach
            <label class="flex items-center gap-2 cursor-pointer text-sm">
                <input type="checkbox" name="os_others" value="1" {{ old('os_others', $checklist->os_others) ? 'checked' : '' }} id="os_others" class="rounded border-black text-emerald-600">
                <span>Others(Specify)</span>
            </label>
            <span id="os_others_wrap" class="{{ old('os_others', $checklist->os_others) ? '' : 'hidden' }} flex items-center">
                <input type="text" name="os_others_specify" value="{{ old('os_others_specify', $checklist->os_others_specify) }}" class="border-0 border-b border-black bg-transparent py-0 text-sm w-full focus:ring-0 focus:border-black">
            </span>
        </div>
    </div>

    {{-- Software/Applications Installed --}}
    <div class="mt-3">
        <h2 class="text-sm font-bold mb-1">Software/Applications Installed</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-1">
            @foreach ($softwareApplications as $item)
            @php $fieldName = 'software_' . strtolower(str_replace([' ', '.', '-'], '_', $item->name)); @endphp
            <label class="flex items-center gap-2 cursor-pointer text-sm">
                <input type="checkbox" name="{{ $fieldName }}" value="1" {{ old($fieldName, $checklist->$fieldName ?? false) ? 'checked' : '' }} class="rounded border-black text-emerald-600">
                <span>{{ $item->name }}</span>
            </label>
            @endforeach
            <label class="flex items-center gap-2 cursor-pointer text-sm">
                <input type="checkbox" name="software_others" value="1" {{ old('software_others', $checklist->software_others) ? 'checked' : '' }} id="software_others" class="rounded border-black text-emerald-600">
                <span>Others(Specify)</span>
            </label>
            <span id="software_others_wrap" class="{{ old('software_others', $checklist->software_others) ? '' : 'hidden' }} flex items-center">
                <input type="text" name="software_others_specify" value="{{ old('software_others_specify', $checklist->software_others_specify) }}" class="border-0 border-b border-black bg-transparent py-0 text-sm w-full focus:ring-0 focus:border-black">
            </span>
        </div>
    </div>

    {{-- Desktop/Laptop Specifications — label + underline style --}}
    <div class="mt-3">
        <h2 class="text-sm font-bold mb-1">Desktop/Laptop Specifications</h2>
        <div class="space-y-0 border border-black">
            @foreach ($specificationFields as $field)
                @if ($field->name !== 'ip_address')
                <div class="flex border-b border-black last:border-b-0">
                    @if ($field->name === 'mac_address')
                        {{-- MAC Address and IP Address combined in one text box --}}
                        <span class="w-44 shrink-0 py-1 px-2 text-sm border-r border-black">{{ $field->label }} & IP</span>
                        <input type="text" name="{{ $field->name }}" value="{{ old($field->name, $checklist->{$field->name}) }} {{ $checklist->ip_address ? '/ ' . $checklist->ip_address : '' }}" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none" placeholder="MAC Address / IP Address">
                        <input type="hidden" name="ip_address" value="{{ old('ip_address', $checklist->ip_address) }}">
                    @else
                        <span class="w-44 shrink-0 py-1 px-2 text-sm border-r border-black">{{ $field->label }}</span>
                        <input type="text" name="{{ $field->name }}" value="{{ old($field->name, $checklist->{$field->name}) }}" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none" {{ $field->placeholder ? 'placeholder="' . $field->placeholder . '"' : '' }}>
                    @endif
                </div>
                @endif
            @endforeach
        </div>
    </div>

    <div class="flex gap-3 mt-3 pb-4">
        <button
            type="submit"
            class="px-4 py-2 bg-black text-white text-sm font-medium hover:bg-gray-800"
            data-loading-text="{{ $isEdit ? 'Updating...' : 'Saving...' }} Checklist"
        >
            {{ $isEdit ? 'Update' : 'Save' }} Checklist
        </button>
        <a href="{{ $isEdit && $submission ? route('preventive-maintenance.show', $submission) : route('preventive-maintenance.index') }}" class="px-4 py-2 border border-black text-sm hover:bg-gray-100">Cancel</a>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Toggle "Others" fields
    const equipmentOthers = document.getElementById('equipment_others');
    const equipmentOthersWrap = document.getElementById('equipment_others_wrap');
    if (equipmentOthers && equipmentOthersWrap) {
        equipmentOthers.addEventListener('change', function () {
            equipmentOthersWrap.classList.toggle('hidden', !this.checked);
        });
    }

    const osOthers = document.getElementById('os_others');
    const osOthersWrap = document.getElementById('os_others_wrap');
    if (osOthers && osOthersWrap) {
        osOthers.addEventListener('change', function () {
            osOthersWrap.classList.toggle('hidden', !this.checked);
        });
    }

    const softwareOthers = document.getElementById('software_others');
    const softwareOthersWrap = document.getElementById('software_others_wrap');
    if (softwareOthers && softwareOthersWrap) {
        softwareOthers.addEventListener('change', function () {
            softwareOthersWrap.classList.toggle('hidden', !this.checked);
        });
    }

    // Simple loading state on submit
    const form = document.getElementById('preventiveMaintenanceForm');
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
