<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $audit->title }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        @page { size: A4; margin: 20mm 15mm; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            line-height: 1.5;
        }

        @media print {
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
            table { page-break-inside: avoid; }
        }

        .print-btn {
            position: fixed;
            top: 16px;
            right: 16px;
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }

        header {
            border-bottom: 3px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        header h1 { font-size: 20pt; color: #1e3a5f; margin-bottom: 4px; }
        header p { color: #555; font-size: 10pt; }

        h2 {
            font-size: 13pt;
            color: #1e3a5f;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 4px;
            margin: 20px 0 10px;
        }

        h3 { font-size: 11pt; color: #374151; margin: 12px 0 6px; }

        .score-badge {
            display: inline-block;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 18pt;
            font-weight: bold;
            color: #fff;
            margin-bottom: 10px;
        }
        .score-green  { background: #16a34a; }
        .score-orange { background: #d97706; }
        .score-red    { background: #dc2626; }

        p { margin-bottom: 8px; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 10pt;
        }
        th {
            background: #e8f0fe;
            color: #1e3a5f;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #c5d3ea;
        }
        td {
            padding: 5px 8px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }
        tr:nth-child(even) td { background: #f9fafb; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9pt;
            font-weight: 600;
        }
        .badge-critical  { background: #fee2e2; color: #b91c1c; }
        .badge-serious   { background: #ffedd5; color: #c2410c; }
        .badge-moderate  { background: #fef9c3; color: #92400e; }
        .badge-minor     { background: #dbeafe; color: #1d4ed8; }
        .badge-high      { background: #fce7f3; color: #9d174d; }
        .badge-medium    { background: #fef9c3; color: #92400e; }
        .badge-low       { background: #dcfce7; color: #15803d; }

        .compliance-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }
        .compliance-card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
        }
        .compliance-card .level  { font-weight: bold; font-size: 12pt; color: #1e3a5f; }
        .compliance-card .status { font-size: 10pt; margin: 4px 0; }
        .status-pass    { color: #16a34a; font-weight: 600; }
        .status-partial { color: #d97706; font-weight: 600; }
        .status-fail    { color: #dc2626; font-weight: 600; }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }
        .stat-box {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
        }
        .stat-box .num  { font-size: 16pt; font-weight: bold; color: #1e3a5f; }
        .stat-box .lbl  { font-size: 9pt; color: #6b7280; }

        .remediation {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
        }
        .remediation h3 { margin-top: 0; }
        .remediation ol { padding-left: 18px; font-size: 10pt; }
        .remediation ol li { margin-bottom: 4px; }

        pre {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 8px;
            font-size: 9pt;
            white-space: pre-wrap;
            word-break: break-all;
            margin-top: 8px;
        }

        footer {
            margin-top: 30px;
            border-top: 1px solid #d1d5db;
            padding-top: 8px;
            font-size: 9pt;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">Print / Save as PDF</button>

{{-- Header --}}
<header>
    <h1>{{ $audit->title }}</h1>
    <p>
        Property: {{ $audit->property?->name ?? '—' }}
        {{ $audit->property?->base_url ? '(' . $audit->property->base_url . ')' : '' }}
        &nbsp;|&nbsp;
        Generated: {{ $audit->generated_at?->format('F j, Y H:i') ?? now()->format('F j, Y H:i') }}
    </p>
</header>

{{-- Overall Score --}}
<h2>Overall Score</h2>
@php
    $score = $audit->overall_score;
    $scoreClass = $score >= 80 ? 'score-green' : ($score >= 50 ? 'score-orange' : 'score-red');
@endphp
<span class="score-badge {{ $scoreClass }}">{{ $score ?? 'N/A' }} / 100</span>

{{-- Executive Summary --}}
<h2>Executive Summary</h2>
@foreach(preg_split('/\n{2,}/', $audit->executive_summary ?? '') as $paragraph)
    <p>{{ $paragraph }}</p>
@endforeach

{{-- Summary Statistics --}}
@if($audit->summary_statistics)
<h2>Summary Statistics</h2>
<div class="stat-grid">
    @foreach(['total_issues' => 'Total', 'critical' => 'Critical', 'serious' => 'Serious', 'moderate' => 'Moderate', 'minor' => 'Minor'] as $key => $label)
    <div class="stat-box">
        <div class="num">{{ $audit->summary_statistics[$key] ?? 0 }}</div>
        <div class="lbl">{{ $label }}</div>
    </div>
    @endforeach
</div>
@endif

{{-- Compliance Status --}}
@if($audit->compliance_status)
<h2>WCAG Compliance Status</h2>
<div class="compliance-grid">
    @foreach(['wcag_a' => 'WCAG A', 'wcag_aa' => 'WCAG AA', 'wcag_aaa' => 'WCAG AAA'] as $key => $label)
    @php $item = $audit->compliance_status[$key] ?? null; @endphp
    <div class="compliance-card">
        <div class="level">{{ $label }}</div>
        <div class="status status-{{ $item['status'] ?? 'fail' }}">{{ ucfirst($item['status'] ?? 'N/A') }}</div>
        <div style="font-size:9.5pt; color:#4b5563;">{{ $item['notes'] ?? '' }}</div>
    </div>
    @endforeach
</div>
@endif

{{-- Top Risks --}}
@if($audit->top_risks)
<h2>Top Risks</h2>
<table>
    <thead>
        <tr>
            <th style="width:40px">#</th>
            <th>Risk</th>
            <th style="width:90px">Severity</th>
            <th style="width:70px">WCAG</th>
            <th>Impact</th>
            <th style="width:80px">Occurrences</th>
        </tr>
    </thead>
    <tbody>
        @foreach($audit->top_risks as $risk)
        <tr>
            <td>{{ $risk['rank'] ?? '' }}</td>
            <td>{{ $risk['title'] ?? '' }}</td>
            <td><span class="badge badge-{{ $risk['severity'] ?? 'minor' }}">{{ ucfirst($risk['severity'] ?? '') }}</span></td>
            <td>{{ $risk['wcag_criteria'] ?? '' }}</td>
            <td>{{ $risk['impact'] ?? '' }}</td>
            <td>{{ $risk['occurrences'] ?? '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Issue Details --}}
@if($audit->issue_details)
<div class="page-break"></div>
<h2>Issue Details</h2>
<table>
    <thead>
        <tr>
            <th>Rule</th>
            <th>Title</th>
            <th style="width:90px">Severity</th>
            <th style="width:65px">WCAG</th>
            <th style="width:70px">Pages</th>
            <th>Quick Fix</th>
        </tr>
    </thead>
    <tbody>
        @foreach($audit->issue_details as $issue)
        <tr>
            <td style="font-size:9pt; font-family:monospace;">{{ $issue['rule_key'] ?? '' }}</td>
            <td>{{ $issue['title'] ?? '' }}</td>
            <td><span class="badge badge-{{ $issue['severity'] ?? 'minor' }}">{{ ucfirst($issue['severity'] ?? '') }}</span></td>
            <td>{{ $issue['wcag_criteria'] ?? '' }}</td>
            <td>{{ $issue['affected_pages'] ?? '' }}</td>
            <td>{{ $issue['remediation_hint'] ?? '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Remediations --}}
@if($audit->remediations)
<div class="page-break"></div>
<h2>Remediations</h2>
@foreach($audit->remediations as $rem)
<div class="remediation">
    <h3>
        <span class="badge badge-{{ $rem['priority'] ?? 'low' }}">{{ ucfirst($rem['priority'] ?? '') }}</span>
        &nbsp;{{ $rem['title'] ?? '' }}
    </h3>
    <p>{{ $rem['description'] ?? '' }}</p>
    @if(!empty($rem['steps']))
    <ol>
        @foreach($rem['steps'] as $step)
        <li>{{ $step }}</li>
        @endforeach
    </ol>
    @endif
    @if(!empty($rem['code_example']))
    <pre>{{ $rem['code_example'] }}</pre>
    @endif
</div>
@endforeach
@endif

<footer>
    AI Accessibility Audit &mdash; {{ $audit->title }} &mdash; {{ now()->format('Y') }}
</footer>

</body>
</html>
