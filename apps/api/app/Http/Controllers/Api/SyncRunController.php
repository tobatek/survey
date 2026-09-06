<?php

namespace App\Http\Controllers\Api;

use App\Domains\Integrations\Models\SyncRun;
use App\Http\Controllers\Controller;
use App\Http\Resources\SyncRunResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// FSD §17.5 GET /api/sync-runs/{id}. index() ditambahkan (di luar FSD
// literal) supaya UI monitoring sync bisa menampilkan histori tanpa perlu
// tahu ID run tertentu lebih dulu — dipakai StudentListPage/EmployeeListPage
// untuk menunjukkan status sync terakhir.
class SyncRunController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SyncRun::query()->latest('started_at');

        if ($entityType = $request->string('entity_type')->value()) {
            $query->where('entity_type', $entityType);
        }

        return SyncRunResource::collection($query->paginate(min($request->integer('per_page', 10), 50)));
    }

    public function show(SyncRun $syncRun): SyncRunResource
    {
        return SyncRunResource::make($syncRun->loadCount('errors'));
    }
}
