<?php

namespace App\Http\Controllers;

use App\Actions\AppAssociations\AppAssociationPayload;
use App\Actions\AppAssociations\ManageAppAssociations;
use App\Http\Requests\AppAssociations\StoreAppAssociationRequest;
use App\Models\AppAssociation;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AppAssociationController extends Controller
{
    public function index(Domain $domain, AppAssociationPayload $payload): Response
    {
        Gate::authorize('manage', $domain);

        return Inertia::render('Domains/Apps', $payload->forDomain($domain));
    }

    public function store(StoreAppAssociationRequest $request, Domain $domain, ManageAppAssociations $associations): RedirectResponse
    {
        $associations->add($request->user(), $domain, $request->association());

        return back();
    }

    public function destroy(Request $request, Domain $domain, AppAssociation $appAssociation, ManageAppAssociations $associations): RedirectResponse
    {
        $associations->remove($request->user(), $appAssociation);

        return back();
    }
}
