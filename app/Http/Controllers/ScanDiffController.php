<?php

namespace App\Http\Controllers;

use App\Enums\ScanStatus;
use App\Models\Finding;
use App\Models\Scan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class ScanDiffController extends Controller
{
    use AuthorizesRequests;

    public function show(Scan $scan): Response
    {
        $this->authorize('view', $scan);

        $scan->load('property:id,name,base_url');

        $priorScan = Scan::query()
            ->where('property_id', $scan->property_id)
            ->where('status', ScanStatus::Completed)
            ->where('id', '<', $scan->id)
            ->latest('id')
            ->first();

        $comparableScan = null;
        $newFindings = collect();
        $resolvedFindings = collect();
        $unchangedCount = 0;

        if ($priorScan) {
            $comparableScan = [
                'id' => $priorScan->id,
                'created_at' => $priorScan->created_at,
                'pages_scanned' => $priorScan->pages_scanned,
                'total_violations' => $priorScan->total_violations,
            ];

            $currentPrints = Finding::query()
                ->where('scan_id', $scan->id)
                ->pluck('fingerprint')
                ->flip();

            $priorPrints = Finding::query()
                ->where('scan_id', $priorScan->id)
                ->pluck('fingerprint')
                ->flip();

            $newPrints = $currentPrints->diffKeys($priorPrints)->keys();
            $resolvedPrints = $priorPrints->diffKeys($currentPrints)->keys();

            $unchangedCount = $currentPrints->intersectByKeys($priorPrints)->count();

            $newFindings = Finding::query()
                ->where('scan_id', $scan->id)
                ->whereIn('fingerprint', $newPrints)
                ->select(['id', 'rule_key', 'severity', 'page_url', 'element_identifier', 'message', 'wcag_criteria'])
                ->orderBy('severity')
                ->orderBy('rule_key')
                ->get()
                ->map(fn (Finding $f) => [
                    'id' => $f->id,
                    'rule_key' => $f->rule_key,
                    'severity' => $f->severity->value,
                    'page_url' => $f->page_url,
                    'element_identifier' => $f->element_identifier,
                    'message' => $f->message,
                    'wcag_criteria' => $f->wcag_criteria,
                ]);

            $resolvedFindings = Finding::query()
                ->where('scan_id', $priorScan->id)
                ->whereIn('fingerprint', $resolvedPrints)
                ->select(['id', 'rule_key', 'severity', 'page_url', 'element_identifier', 'message', 'wcag_criteria'])
                ->orderBy('severity')
                ->orderBy('rule_key')
                ->get()
                ->map(fn (Finding $f) => [
                    'id' => $f->id,
                    'rule_key' => $f->rule_key,
                    'severity' => $f->severity->value,
                    'page_url' => $f->page_url,
                    'element_identifier' => $f->element_identifier,
                    'message' => $f->message,
                    'wcag_criteria' => $f->wcag_criteria,
                ]);
        }

        return Inertia::render('scans/diff', [
            'scan' => [
                'id' => $scan->id,
                'status' => $scan->status->value,
                'pages_scanned' => $scan->pages_scanned,
                'total_violations' => $scan->total_violations,
                'created_at' => $scan->created_at,
                'property' => $scan->property ? [
                    'id' => $scan->property->id,
                    'name' => $scan->property->name,
                    'base_url' => $scan->property->base_url,
                ] : null,
            ],
            'comparableScan' => $comparableScan,
            'newFindings' => $newFindings,
            'resolvedFindings' => $resolvedFindings,
            'unchangedCount' => $unchangedCount,
        ]);
    }
}
