<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risk Advisory #{{ $advisory->id }}</title>
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
        p { margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 10pt; }
        th { background: #e8f0fe; color: #1e3a5f; text-align: left; padding: 6px 8px; border: 1px solid #c5d3ea; }
        td { padding: 5px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #f9fafb; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9pt; font-weight: 600; }
        .badge-critical { background: #fee2e2; color: #b91c1c; }
        .badge-serious { background: #ffedd5; color: #c2410c; }
        .badge-high { background: #fce7f3; color: #9d174d; }
        .badge-moderate { background: #fef9c3; color: #92400e; }
        .badge-medium { background: #fef9c3; color: #92400e; }
        .badge-minor { background: #dbeafe; color: #1d4ed8; }
        .badge-low { background: #dcfce7; color: #15803d; }
        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 14px; }
        .stat-box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px; text-align: center; }
        .stat-box .num { font-size: 16pt; font-weight: bold; color: #1e3a5f; }
        .stat-box .lbl { font-size: 9pt; color: #6b7280; }
        footer { margin-top: 30px; border-top: 1px solid #d1d5db; padding-top: 8px; font-size: 9pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">Print / Save as PDF</button>

<header>
    <h1>Risk Advisory</h1>
    <p>
        @if($advisory->property)
            Property: {{ $advisory->property->name }}
            {{ $advisory->property->base_url ? '(' . $advisory->property->base_url . ')' : '' }}
            &nbsp;|&nbsp;
        @endif
        Generated: {{ $advisory->generated_at?->format('F j, Y H:i') ?? now()->format('F j, Y H:i') }}
    </p>
</header>

{{-- Summary Stats --}}
<h2>Summary</h2>
<div class="stat-grid">
    <div class="stat-box">
        <div class="num">{{ $advisory->total_recommendations ?? 0 }}</div>
        <div class="lbl">Recommendations</div>
    </div>
    <div class="stat-box">
        <div class="num">{{ $advisory->issues_analyzed ?? 0 }}</div>
        <div class="lbl">Issues Analyzed</div>
    </div>
    <div class="stat-box">
        <div class="num">{{ count($advisory->priorities ?? []) }}</div>
        <div class="lbl">Priorities</div>
    </div>
</div>

{{-- Priorities Table --}}
@if($advisory->priorities)
<h2>Prioritized Recommendations</h2>
<table>
    <thead>
        <tr>
            <th style="width:50px">Rank</th>
            <th>Rule Key</th>
            <th style="width:90px">Severity</th>
            <th style="width:80px">Risk Score</th>
            <th>Rationale</th>
            <th>Recommended Action</th>
        </tr>
    </thead>
    <tbody>
        @foreach($advisory->priorities as $priority)
        <tr>
            <td>{{ $priority['rank'] ?? '' }}</td>
            <td style="font-family: monospace; font-size: 9pt;">{{ $priority['rule_key'] ?? '' }}</td>
            <td><span class="badge badge-{{ strtolower($priority['severity'] ?? 'low') }}">{{ $priority['severity'] ?? '' }}</span></td>
            <td>{{ $priority['risk_reduction_score'] ?? '' }}</td>
            <td>{{ $priority['rationale'] ?? '' }}</td>
            <td>{{ $priority['recommended_action'] ?? '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<footer>
    Risk Advisory #{{ $advisory->id }} &middot; Generated {{ now()->format('F j, Y') }}
</footer>

</body>
</html>
