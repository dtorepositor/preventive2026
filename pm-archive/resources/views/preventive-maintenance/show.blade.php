@extends('layouts.app')

@section('title', 'Checklist – ' . ($checklist->pc_name ?: 'Preventive Maintenance'))

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Preventive Maintenance Checklist</h2>
        <p class="text-slate-600 text-sm mt-1">{{ $checklist->pc_name ?: 'No PC name' }} &middot; {{ $checklist->checklist_date ? $checklist->checklist_date->format('M d, Y') : 'No date' }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('preventive-maintenance.edit', $submission) }}" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 font-medium">Edit</a>
        <a href="{{ route('preventive-maintenance.index') }}" class="px-4 py-2 text-slate-600 hover:underline">Back to list</a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-4">User and PC</h3>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
        <div><dt class="text-slate-500">User/Operator</dt><dd class="font-medium">{{ $checklist->user_operator ?: '—' }}</dd></div>
        <div><dt class="text-slate-500">Office/College</dt><dd class="font-medium">{{ $checklist->office_college ?: '—' }}</dd></div>
        <div><dt class="text-slate-500">Department</dt><dd class="font-medium">{{ $checklist->department ?: '—' }}</dd></div>
        <div><dt class="text-slate-500">Date Acquired</dt><dd class="font-medium">{{ $checklist->date_acquired ? $checklist->date_acquired->format('M d, Y') : '—' }}</dd></div>
        <div><dt class="text-slate-500">PC Name</dt><dd class="font-medium">{{ $checklist->pc_name ?: '—' }}</dd></div>
    </dl>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-4">Equipment &amp; OS &amp; Software</h3>
    <div class="flex flex-wrap gap-4 text-sm">
        @foreach ($equipment as $item)
        @php $fieldName = 'equipment_' . strtolower(str_replace(' ', '_', $item->name)); @endphp
        @if ($checklist->$fieldName) <span class="px-2 py-1 bg-slate-100 rounded">{{ $item->name }}</span> @endif
        @endforeach
        @if ($checklist->equipment_others && $checklist->equipment_others_specify) <span class="px-2 py-1 bg-slate-100 rounded">Others: {{ $checklist->equipment_others_specify }}</span> @endif
    </div>
    <p class="text-slate-500 text-sm mt-2">OS: 
        @foreach ($operatingSystems as $item)
        @php $fieldName = 'os_' . strtolower(str_replace([' ', '.', '-'], '_', $item->name)); @endphp
        @if ($checklist->$fieldName) {{ $item->name }} @endif
        @endforeach
        @if ($checklist->os_others && $checklist->os_others_specify) {{ $checklist->os_others_specify }} @endif
    </p>
    <p class="text-slate-500 text-sm">Software: 
        @foreach ($softwareApplications as $item)
        @php $fieldName = 'software_' . strtolower(str_replace([' ', '.', '-'], '_', $item->name)); @endphp
        @if ($checklist->$fieldName) {{ $item->name }} @endif
        @endforeach
        @if ($checklist->software_others && $checklist->software_others_specify) {{ $checklist->software_others_specify }} @endif
    </p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-4">Specifications</h3>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
        @foreach ($specificationFields as $field)
        @if ($field->name !== 'ip_address')
            @if ($field->name === 'mac_address')
                @if ($checklist->{$field->name} || $checklist->ip_address)
                <div><dt class="text-slate-500">{{ $field->label }} & IP</dt><dd>{{ $checklist->{$field->name} ?: '—' }}{{ $checklist->ip_address ? ' / ' . $checklist->ip_address : '' }}</dd></div>
                @endif
            @else
                @if ($checklist->{$field->name})
                <div><dt class="text-slate-500">{{ $field->label }}</dt><dd>{{ $checklist->{$field->name} }}</dd></div>
                @endif
            @endif
        @endif
        @endforeach
    </dl>
</div>

@if ($itemChecklists->isNotEmpty())
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-4">Item Checklists (Maintenance Records)</h3>

    <div class="flex flex-col gap-3">
        @foreach ($itemChecklists as $ic)
            @php $icDate = $ic->getValueByVarName('maintenance_date'); @endphp
            <div class="flex items-center gap-3">
                <button type="button" onclick="window.location='{{ route('item-checklist.edit', $ic) }}';" class="text-emerald-600 hover:underline text-sm">
                    {{ $icDate ? \Carbon\Carbon::parse($icDate)->format('M d, Y') : 'View' }}
                </button>

                <a href="{{ route("item-checklist.edit", $ic) }}" class="text-emerald-600 hover:underline text-sm">Edit</a>

                <form action="{{ route("item-checklist.destroy", $ic) }}" method="POST" class="inline delete-form" data-message="Delete this maintenance record?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline text-sm">Delete</button>
                </form>
            </div>
        @endforeach
    </div>

</div>
@endif
@endsection
