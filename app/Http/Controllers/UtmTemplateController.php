<?php

namespace App\Http\Controllers;

use App\Http\Requests\UtmTemplates\UtmTemplateRequest;
use App\Models\UtmTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class UtmTemplateController extends Controller
{
    public function store(UtmTemplateRequest $request): RedirectResponse
    {
        $request->workspace()->utmTemplates()->create($request->templateAttributes());

        return back();
    }

    public function update(UtmTemplateRequest $request, UtmTemplate $utmTemplate): RedirectResponse
    {
        $utmTemplate->update($request->templateAttributes());

        return back();
    }

    public function destroy(UtmTemplate $utmTemplate): RedirectResponse
    {
        Gate::authorize('delete', $utmTemplate);

        $utmTemplate->delete();

        return back();
    }
}
