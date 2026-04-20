<?php

namespace App\Http\Controllers;

use App\Models\PdfDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class PdfDocumentController extends Controller
{
    use AuthorizesRequests;

    public function show(PdfDocument $pdfDocument): Response
    {
        $this->authorize('view', $pdfDocument);

        $pdfDocument->load(['scan:id,status', 'property:id,name,base_url', 'violations']);

        return Inertia::render('pdf-documents/show', [
            'document' => [
                'id' => $pdfDocument->id,
                'url' => $pdfDocument->url,
                'filename' => $pdfDocument->filename,
                'status' => $pdfDocument->status->value,
                'violation_count' => $pdfDocument->violation_count,
                'error_message' => $pdfDocument->error_message,
                'scanned_at' => $pdfDocument->scanned_at?->toIso8601String(),
                'property' => [
                    'id' => $pdfDocument->property->id,
                    'name' => $pdfDocument->property->name,
                    'base_url' => $pdfDocument->property->base_url,
                ],
                'scan' => [
                    'id' => $pdfDocument->scan->id,
                    'status' => $pdfDocument->scan->status->value,
                ],
                'violations' => $pdfDocument->violations->map(fn ($v) => [
                    'id' => $v->id,
                    'rule_key' => $v->rule_key,
                    'severity' => $v->severity->value,
                    'wcag_criteria' => $v->wcag_criteria,
                    'description' => $v->description,
                    'element_context' => $v->element_context,
                    'page_number' => $v->page_number,
                ]),
            ],
        ]);
    }
}
