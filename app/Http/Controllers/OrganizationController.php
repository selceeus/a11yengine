<?php

namespace App\Http\Controllers;

use App\Models\Agency;

class OrganizationController extends Controller
{
    public function __construct(private readonly Agency $agency) {}

    public function index()
    {
        $organizations = $this->agency->organizations()->get();
    }
}
