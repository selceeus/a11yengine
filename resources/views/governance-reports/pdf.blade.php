<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Governance Report #{{ $report->id }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        @page { size: A4; margin: 20mm 15mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11pt; color: #1a1a1a; line-height: 1.5; }
        @media print {
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
            table { page-break-inside: avoid; }
        }
        .print-btn { position: fixed; top: 16px; right: 16px; background: #2563eb; color: #fff; border: none; padding: 8px 18px; border-radius: 6px; font-size: 14px; cursor: pointer; }
        header { border-bottom: 3px solid #2563eb; padding-bottom: 12px; margin-bottom: 20px; }
        header h1 { font-size: 20pt; color: #1e3a5f; margin-bottom: 4px; }
        header p { color: #555; font-size: 10pt; }
        h2 { font-size: 13pt; color: #1e3a5f; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; margin: 20px 0 10px; }
        h3 { font-size: 11pt; color: #374151; margin: 12px 0 6px; }
        p { margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 10pt; }
        th { background: #e8f0fe; color: #1e3a5f; text-align: left; padding: 6px 8px; border: 1px solid #c5d3ea; }
        td { padding: 5px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #f9fafb; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9pt; font-weight: 600; }
        .badge-critical { background: #fee2e2; color: #b91c1c; }
        .badge-high { background: #fce7f3; color: #9d174d; }
        .badge-medium { background: #fef9c3; color: #92400e; }
        .badge-low { background: #dcfce7; color: #15803d; }
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 14px; }
        .stat-box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px; text-align: center; }
        .stat-box .num { font-size: 16pt; font-weight: bold; color: #1e3a5f; }
        .stat-box .lbl { font-size: 9pt; color: #6b7280; }
        .compliance-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 14px; }
        .compliance-card { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; }
        .compliance-card .level { font-weight: bold; font-size: 12pt; color: #1e3a5f; }
        .status-pass { color: #16a34a; font-weight: 600; }
        .status-partial { color: #d97706; font-weight: 600; }
        .status-fail { color: #dc2626; font-weight: 600; }
        footer { margin-top: 30px; border-top: 1px solid #d1d5db; padding-top: 8px; font-size: 9pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">Print / Save as PDF</button>

<header>
    <h1>Governance Report</h1>
    <p>
        @if($report->property)
            Property: {{ $report->property->name }}
            {{ $report->property->base_url ? '(' . $report->property->base_url . ')' : '' }}
            &nbsp;|&nbsp;
        @endif
        Period: {{ $report->period_from->format('M j, Y') }} — {{ $report->period_to->format('M j, Y') }}
        &nbsp;|&nbsp;
        Generated: {{ $report->generated_at?->format('F j, Y H:i') ?? now()->format('F j, Y H:i') }}
    </p>
</header>

{{-- Summary Cards --}}
@if($report->summary_cards)
<h2>Summary</h2>
<div class="stat-grid">
    @foreach($report->summary_cards as $card)
    <div class="stat-box">
        <div class="num">{{ $card['value'] ?? '—' }}</div>
        <div class="lbl">{{ $card['label'] ?? '' }}</div>
    </div>
    @endforeach
</div>
@endif

{{-- Executive Narrative --}}
@if($report->executive_narrative)
<h2>Executive Narrative</h2>
@foreach(preg_split('/\n{2,}/', $report->executive_narrative) as $paragraph)
    <p>{{ $paragraph }}</p>
@endforeach
@endif

{{-- Severity Breakdown --}}
@if($report->severity_breakdown)
<h2>Severity Breakdown</h2>
<table>
    <thead><tr><th>Severity</th><th>Count</th><th>Percentage</th></tr></thead>
    <tbody>
        @foreach($report->severity_breakdown as $item)
        <tr>
            <td><span class="badge badge-{{ strtolower($item['severity'] ?? 'low') }}">{{ $item['severity'] ?? '' }}</span></td>
            <td>{{ $item['count'] ?? 0 }}</td>
            <td>{{ $item['percentage'] ?? '0' }}%</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Compliance Status --}}
@if($report->compliance_status)
<h2>Compliance Status</h2>
<div class="compliance-grid">
    @foreach($report->compliance_status as $item)
    <div class="compliance-card">
        <div class="level">{{ $item['level'] ?? $item['criteria'] ?? '' }}</div>
        <div class="status-{{ $item['status'] ?? 'fail' }}">{{ ucfirst($item['status'] ?? 'N/A') }}</div>
        <div style="font-size:9.5pt; color:#4b5563;">{{ $item['notes'] ?? '' }}</div>
    </div>
    @endforeach
</div>
@endif

{{-- Remediation Progress --}}
@if($report->remediation_progress)
<h2>Remediation Progress</h2>
<table>
    <thead><tr><th>Category</th><th>Resolved</th><th>Total</th><th>Progress</th></tr></thead>
    <tbody>
        @foreach($report->remediation_progress as $item)
        <tr>
            <td>{{ $item['category'] ?? '' }}</td>
            <td>{{ $item['resolved'] ?? 0 }}</td>
            <td>{{ $item['total'] ?? 0 }}</td>
            <td>{{ ($item['total'] ?? 0) > 0 ? round(($item['resolved'] ?? 0) / $item['total'] * 100) : 0 }}%</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Recommendations --}}
@if($report->recommendations)
<div class="page-break"></div>
<h2>Recommendations</h2>
<table>
    <thead>
        <tr>
            <th style="width:80px">Priority</th>
            <th>Title</th>
            <th>Category</th>
            <th>Action</th>
            <th style="width:80px">Due By</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report->recommendations as $rec)
        <tr>
            <td><span class="badge badge-{{ strtolower($rec['priority'] ?? 'low') }}">{{ $rec['priority'] ?? '' }}</span></td>
            <td>{{ $rec['title'] ?? '' }}</td>
            <td>{{ $rec['category'] ?? '' }}</td>
            <td>{{ $rec['action'] ?? '' }}</td>
            <td>{{ $rec['due_by_quarter'] ?? '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<footer>
    Governance Report #{{ $report->id }} &middot; Generated {{ now()->format('F j, Y') }}
</footer>

</body>
</html>
