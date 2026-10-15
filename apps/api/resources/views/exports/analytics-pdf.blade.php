<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 24px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; font-size: 10px; }
        th { background: #f3f4f6; }
        .cover { text-align: center; padding-top: 120px; }
        .cover .subtitle { color: #555; margin-top: 8px; }
        .footer-note { margin-top: 24px; font-size: 9px; color: #666; }
        .chart { margin-top: 12px; text-align: center; }
        .chart img { max-width: 100%; }
    </style>
</head>
<body>
    <div class="cover">
        <h1>{{ $questionnaireTitle }}</h1>
        <div class="subtitle">myAMETA Survey -- Export Hasil Analitik (PDF)</div>
        <div class="subtitle">Dibuat: {{ $generatedAt }}</div>
        <div class="subtitle">Versi dokumen: {{ $version }}</div>
    </div>

    <div style="page-break-after: always;"></div>

    <h2>Metadata &amp; Filter</h2>
    <table>
        <tr><th>Campaign ID</th><td>{{ $campaign->id }}</td></tr>
        <tr><th>Ambang batas segmen (minimum_segment_size)</th><td>{{ $campaign->minimum_segment_size }}</td></tr>
        <tr><th>Dibuat oleh</th><td>{{ $generatedByLabel }}</td></tr>
        @foreach ($filterLines as $label => $value)
            <tr><th>Filter: {{ $label }}</th><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    <h2>Response Funnel</h2>
    @if ($summary['suppressed'] ?? false)
        <p>Data disembunyikan -- jumlah responden di bawah ambang batas minimum_segment_size.</p>
    @else
        <table>
            <tr><th>Status</th><th>Jumlah</th></tr>
            @foreach ($summary['funnel'] as $status => $count)
                <tr><td>{{ $status }}</td><td>{{ $count }}</td></tr>
            @endforeach
            <tr><th>Response rate</th><td>{{ $summary['response_rate']['value'] ?? '-' }}%</td></tr>
            <tr><th>Completion rate</th><td>{{ $summary['completion_rate']['value'] ?? '-' }}%</td></tr>
            <tr><th>Total responses</th><td>{{ $summary['total_responses'] }}</td></tr>
        </table>
    @endif
    <div class="chart">
        <img src="data:image/png;base64,{{ $chartBase64 }}" alt="Funnel Respons">
    </div>

    <h2>Question Results</h2>
    <table>
        <tr><th>Section</th><th>Kode</th><th>Pertanyaan</th><th>Tipe</th><th>n</th><th>Ringkasan</th></tr>
        @foreach ($questionRows as $row)
            <tr>
                <td>{{ $row['section'] }}</td>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['text'] }}</td>
                <td>{{ $row['type'] }}</td>
                <td>{{ $row['n'] }}</td>
                <td>{{ $row['summary'] }}</td>
            </tr>
        @endforeach
    </table>

    <div class="footer-note">
        Catatan rumus/denominator: <code>n</code> adalah jumlah jawaban valid (tidak termasuk
        "tidak berlaku"). Persentase dihitung dari <code>n</code> sebagai penyebut, bukan dari total
        audiens. Kelompok dengan jumlah responden di bawah ambang batas segmen (minimum_segment_size)
        disensor untuk mencegah re-identifikasi (FSD §12.2). Dihasilkan otomatis oleh myAMETA Survey,
        {{ $generatedAt }}.
    </div>
</body>
</html>
