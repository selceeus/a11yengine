<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Audit #{{ $audit->id }}</title>
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
        .badge-high { background: #fce7f3; color: #9d174d; }
        .badge-medium { background: #fef9c3; color: #92400e; }
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
    <h1>Content Audit</h1>
    <p>
        @if($audit->property)
            Property: {{ $audit->property->name }}
            {{ $audit->property->base_url ? '(' . $audit->property->base_url . ')' : '' }}
            &nbsp;|&nbsp;
        @endif
        Generated: {{ $audit->generated_at?->format('F j, Y H:i') ?? now()->format('F j, Y H:i') }}
    </p>
</header>

{{-- Summary Stats --}}
<h2>Summary</h2>
<div class="stat-grid">
    <div class="stat-box">
        <div class="num">{{ $audit->total_issues ?? 0 }}</div>
        <div class="lbl">Total Issues</div>
    </div>
    <div class="stat-box">
        <div class="num">{{ $audit->pages_analyzed ?? 0 }}</div>
        <div class="lbl">Pages Analyzed</div>
    </div>
    <div class="stat-box">
        <div class="num">{{ count($audit->content_issues ?? []) }}</div>
        <div class="lbl">Content Issues</div>
    </div>
</div>

{{-- Content Issues Table --}}
@if($audit->content_issues)
<h2>Content Issues</h2>
<table>
    <thead>
        <tr>
            <th style="width:90px">Type</th>
            <th style="width:80px">Severity</th>
            <th>Page URL</th>
            <th>Description</th>
            <th>Element</th>
            <th>Recommendation</th>
        </tr>
    </thead>
    <tbody>
        @foreach($audit->content_issues as $issue)
        <tr>
            <td>{{ $issue['type'] ?? '' }}</td>
            <td><span class="badge badge-{{ strtolower($issue['severity'] ?? 'low') }}">{{ $issue['severity'] ?? '' }}</span></td>
            <td style="font-size: 9pt; word-break: break-all;">{{ $issue['page_url'] ?? '' }}</td>
            <td>{{ $issue['description'] ?? '' }}</td>
            <td style="font-family: monospace; font-size: 9pt;">{{ $issue['element'] ?? '' }}</td>
            <td>{{ $issue['recommendation'] ?? '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<footer>
    Content Audit #{{ $audit->id }} &middot; Generated {{ now()->format('F j, Y') }}
</footer>

</body>
</html>
