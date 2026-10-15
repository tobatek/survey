<?php

namespace App\Http\Controllers\Api;

use App\Domains\Employees\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Jobs\SyncEmployeesJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Employee::query();

        if ($search = $request->string('search')->trim()->value()) {
            $needle = mb_strtolower($search);
            $query->where(function ($q) use ($needle) {
                $q->where('employee_number', 'like', "{$needle}%")
                    ->orWhere('normalized_name', 'like', "%{$needle}%");
            });
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($employeeType = $request->string('employee_type')->value()) {
            $query->where('employee_type', $employeeType);
        }

        if ($workUnit = $request->string('work_unit')->value()) {
            $query->where('work_unit_name', $workUnit);
        }

        $perPage = min($request->integer('per_page', 15), 100);

        return EmployeeResource::collection($query->orderBy('name')->paginate($perPage));
    }

    public function sync(Request $request): JsonResponse
    {
        SyncEmployeesJob::dispatch('manual', Auth::id());

        return response()->json([
            'message' => 'Sinkronisasi data pegawai dijadwalkan dan berjalan di latar belakang.',
        ], 202);
    }
}
