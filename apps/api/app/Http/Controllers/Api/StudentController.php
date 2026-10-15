<?php

namespace App\Http\Controllers\Api;

use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Jobs\SyncStudentsJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

// FSD §8.1 daftar mahasiswa: search NIM/nama, filter server-side, pagination
// server-side. Search TIDAK memakai wildcard prefix pada kolom besar tanpa
// indeks (FSD §8.1 catatan) — di sini dibatasi ke normalized_nim/normalized_name
// yang sudah diindeks (lihat migration create_students_table).
class StudentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Student::query();

        if ($search = $request->string('search')->trim()->value()) {
            $needle = mb_strtolower($search);
            $query->where(function ($q) use ($needle) {
                $q->where('normalized_nim', 'like', "{$needle}%")
                    ->orWhere('normalized_name', 'like', "%{$needle}%");
            });
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($programStudyCode = $request->string('program_study_code')->value()) {
            $query->where('program_study_code', $programStudyCode);
        }

        if ($cohortYear = $request->integer('cohort_year')) {
            $query->where('cohort_year', $cohortYear);
        }

        $perPage = min($request->integer('per_page', 15), 100);

        return StudentResource::collection($query->orderBy('name')->paginate($perPage));
    }

    public function sync(Request $request): JsonResponse
    {
        SyncStudentsJob::dispatch('manual', Auth::id());

        return response()->json([
            'message' => 'Sinkronisasi data mahasiswa dijadwalkan dan berjalan di latar belakang.',
        ], 202);
    }
}
