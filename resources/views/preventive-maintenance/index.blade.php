@extends('layouts.app')

@section('title', 'Preventive Maintenance Checklists')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Preventive Maintenance Checklists</h2>
    <a href="{{ route('preventive-maintenance.create') }}" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium">
        + New Preventive Checklist
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">PC Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">User / Operator</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Date</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            @forelse ($checklists as $c)
            @php
                $pcName = $c->name ?: $c->getValueByVarName('pc_name');
                $userOp = $c->getValueByVarName('user_operator');
                $dateVal = $c->getValueByVarName('checklist_date');
                $checklistType = $c->getValueByVarName('checklist_type');
                $timeStr = $c->created_at ? $c->created_at->timezone(config('app.timezone'))->format('h:i A') : null;
                $dateStr = $dateVal ? \Carbon\Carbon::parse($dateVal)->format('M d, Y') : '—';
            @endphp
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $pcName ?: '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-600">{{ $userOp ?: '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-600">
                    <div>{{ $dateStr }}</div>
                    @if ($timeStr)
                        <div class="mt-1 text-xs font-medium text-slate-500 leading-tight">{{ $timeStr }}</div>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('preventive-maintenance.show', $c) }}" class="text-emerald-600 hover:underline text-sm">View</a>
                    <a href="{{ route('preventive-maintenance.edit', $c) }}" class="ml-2 text-slate-600 hover:underline text-sm">Edit</a>
                    <form action="{{ route('preventive-maintenance.destroy', $c) }}" method="POST" class="inline ml-2 delete-form" data-message="Delete this checklist?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline text-sm">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-4 py-8 text-center text-slate-500">
                    No checklists yet. <a href="{{ route('preventive-maintenance.create') }}" class="text-emerald-600 hover:underline">Create one</a>.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if ($checklists->hasPages())
    <div class="px-4 py-3 border-t border-slate-200">
        {{ $checklists->links() }}
    </div>
    @endif
</div>
@endsection
