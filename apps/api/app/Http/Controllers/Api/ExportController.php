<?php

namespace App\Http\Controllers\Api;

use App\Domains\Analytics\Actions\CreateExportJobAction;
use App\Domains\Analytics\Models\ExportJob;
use App\Domains\Campaigns\Models\Campaign;
use App\Http\Controllers\Api\Concerns\ResolvesAnalyticsFilter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

// FSD §14.2/§17.6 (Fase 5 slice 5). Otorisasi SELALU lewat Gate::authorize
// terhadap Campaign terkait (bukan ExportPolicy tersendiri) -- ExportJob
// tidak punya aturan akses independen di luar "bisa lihat analitik campaign
// ini", sama seperti endpoint summary/questions (AnalyticsController).
class ExportController extends Controller
{
    use ResolvesAnalyticsFilter;

    public function store(Request $request, CreateExportJobAction $action): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'uuid', 'exists:campaigns,id'],
            'format' => ['required', 'string', 'in:xlsx,csv'],
            'include_comments' => ['sometimes', 'boolean'],
        ]);

        if ($data['include_comments'] ?? false) {
            abort(422, 'Export komentar belum didukung.');
        }

        $campaign = Campaign::query()->findOrFail($data['campaign_id']);
        Gate::authorize('view', $campaign);

        $filter = $this->filterFromRequest($request);

        $exportJob = $action->execute($campaign, Auth::user(), $filter, $data['format']);

        return response()->json(['data' => $this->toResource($exportJob)], 202);
    }

    public function show(ExportJob $export): JsonResponse
    {
        Gate::authorize('view', $export->campaign);

        return response()->json(['data' => $this->toResource($export)]);
    }

    public function download(Request $request, ExportJob $export): mixed
    {
        Gate::authorize('view', $export->campaign);

        abort_unless($export->status === ExportJob::STATUS_SUCCEEDED, 404);
        abort_if($export->expires_at === null || $export->expires_at->isPast(), 410);
        abort_unless($export->file_path !== null && Storage::disk('local')->exists($export->file_path), 404);

        return Storage::disk('local')->download($export->file_path, $this->downloadFilename($export));
    }

    /**
     * @return array<string, mixed>
     */
    private function toResource(ExportJob $exportJob): array
    {
        $downloadUrl = null;

        if ($exportJob->status === ExportJob::STATUS_SUCCEEDED && $exportJob->expires_at?->isFuture()) {
            $downloadUrl = URL::temporarySignedRoute(
                'exports.download',
                now()->addMinutes(ExportJob::SIGNED_URL_TTL_MINUTES),
                ['export' => $exportJob->id],
            );
        }

        return [
            'id' => $exportJob->id,
            'status' => $exportJob->status,
            'format' => $exportJob->format,
            'requested_at' => $exportJob->requested_at?->toIso8601String(),
            'started_at' => $exportJob->started_at?->toIso8601String(),
            'completed_at' => $exportJob->completed_at?->toIso8601String(),
            'expires_at' => $exportJob->expires_at?->toIso8601String(),
            'file_size' => $exportJob->file_size,
            'error_code' => $exportJob->error_code,
            'download_url' => $downloadUrl,
        ];
    }

    private function downloadFilename(ExportJob $exportJob): string
    {
        $date = $exportJob->completed_at?->toDateString() ?? now()->toDateString();

        return "export-{$exportJob->campaign_id}-{$date}.{$exportJob->format}";
    }
}
