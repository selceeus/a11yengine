<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Agency;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(private readonly Agency $agency) {}

    public function index(): Response
    {
        $organizations = $this->agency->organizations()->get();

        return Inertia::render('organizations/index', [
            'organizations' => $organizations,
        ]);
    }

    public function store(StoreOrganizationRequest $request): \Illuminate\Http\RedirectResponse
    {
        $this->agency->organizations()->create($request->validated());

        return redirect()->back();
    }
}
