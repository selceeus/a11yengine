<?php

namespace App\Policies;

use App\Models\PdfDocument;
use App\Models\User;

class PdfDocumentPolicy
{
    public function view(User $user, PdfDocument $pdfDocument): bool
    {
        return $user->agency_id === $pdfDocument->agency_id;
    }
}
