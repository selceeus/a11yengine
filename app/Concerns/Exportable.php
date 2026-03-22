<?php

namespace App\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait Exportable
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function exportJson(array $data, string $filename): JsonResponse
    {
        return response()->json($data)
            ->withHeaders(['Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }

    /**
     * @param  list<list<scalar|null>>  $rows
     * @param  list<string>  $headers
     */
    protected function exportCsv(array $rows, array $headers, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $headers): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function exportPdf(string $view, array $data, string $filename): Response
    {
        $html = view($view, $data)->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
