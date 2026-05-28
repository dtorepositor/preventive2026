<?php

namespace App\Http\Controllers;

use App\Models\CollegeOffice;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollegeOfficeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            CollegeOffice::query()
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:college_offices,name'],
        ]);

        $collegeOffice = CollegeOffice::create($validated);

        return response()->json($collegeOffice, 201);
    }

    public function update(Request $request, CollegeOffice $collegeOffice): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('college_offices', 'name')->ignore($collegeOffice->id),
            ],
        ]);

        $collegeOffice->update($validated);

        return response()->json($collegeOffice);
    }

    public function destroy(CollegeOffice $collegeOffice): JsonResponse
    {
        $collegeOffice->delete();

        return response()->json(['message' => 'College/Office deleted successfully.']);
    }

    public function departments(CollegeOffice $collegeOffice): JsonResponse
    {
        return response()->json(
            $collegeOffice->departments()
                ->orderBy('name')
                ->get(['id', 'college_office_id', 'name'])
        );
    }

    public function storeDepartment(Request $request, CollegeOffice $collegeOffice): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->where(fn ($query) => $query->where('college_office_id', $collegeOffice->id)),
            ],
        ]);

        $department = $collegeOffice->departments()->create($validated);

        return response()->json($department, 201);
    }

    public function updateDepartment(Request $request, CollegeOffice $collegeOffice, Department $department): JsonResponse
    {
        $this->ensureDepartmentBelongsToCollegeOffice($collegeOffice, $department);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->where(fn ($query) => $query->where('college_office_id', $collegeOffice->id))
                    ->ignore($department->id),
            ],
        ]);

        $department->update($validated);

        return response()->json($department);
    }

    public function destroyDepartment(CollegeOffice $collegeOffice, Department $department): JsonResponse
    {
        $this->ensureDepartmentBelongsToCollegeOffice($collegeOffice, $department);
        $department->delete();

        return response()->json(['message' => 'Department deleted successfully.']);
    }

    private function ensureDepartmentBelongsToCollegeOffice(CollegeOffice $collegeOffice, Department $department): void
    {
        abort_unless((int) $department->college_office_id === (int) $collegeOffice->id, 404);
    }
}
