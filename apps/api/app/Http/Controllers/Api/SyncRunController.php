<?php

namespace App\Http\Controllers\Api;

use App\Domains\Integrations\Models\SyncRun;
use App\Http\Controllers\Controller;
use App\Http\Resources\SyncRunResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

// FSD §17.5 GET /api/sync-runs/{id}. index() ditambahkan (di luar FSD
// literal) supaya UI monitoring sync bisa menampilkan histori tanpa perlu
// tahu ID run tertentu lebih dulu — dipakai StudentListPage/EmployeeListPage
// untuk menunjukkan status sync terakhir.
//
// Fase 6 paket F (celah nyata ditemukan saat sweep security test): SEBELUM
// ini tidak ada filter tenant_id sama sekali di sini, padahal kolomnya
// sudah ada dari awal (`sync_runs.tenant_id`, index gabungan tenant_id+
// entity_type+status) -- satu tenant bisa melihat histori sync tenant lain
// (index() tampil campur, show() bisa ditebak lewat UUID). Middleware
// `role:...` di routes/api.php cuma gerbang role kasar, pola sama semua
// resource lain di project ini (lihat QuestionnairePolicy dkk).
class SyncRunController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SyncRun::query()->where('tenant_id', Auth::user()->tenant_id)->latest('started_at');

        if ($entityType = $request->string('entity_type')->value()) {
            $query->where('entity_type', $entityType);
        }

        return SyncRunResource::collection($query->paginate(min($request->integer('per_page', 10), 50)));
    }

    public function show(SyncRun $syncRun): SyncRunResource
    {
        abort_unless($syncRun->tenant_id === Auth::user()->tenant_id, 403);

        return SyncRunResource::make($syncRun->loadCount('errors'));
    }
}
