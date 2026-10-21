<?php

namespace App\Http\Controllers;

use App\Http\Requests\UtmTemplates\UtmTemplateRequest;
use App\Models\UtmTemplate;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UtmTemplateController extends Controller
{
    public function store(UtmTemplateRequest $request): RedirectResponse
    {
        $this->savingUniqueName(fn () => $request->workspace()->utmTemplates()->create($request->templateAttributes()));

        return back();
    }

    public function update(UtmTemplateRequest $request, UtmTemplate $utmTemplate): RedirectResponse
    {
        $this->savingUniqueName(fn () => $utmTemplate->update($request->templateAttributes()));

        return back();
    }

    public function destroy(UtmTemplate $utmTemplate): RedirectResponse
    {
        Gate::authorize('delete', $utmTemplate);

        $utmTemplate->delete();

        return back();
    }

    private function savingUniqueName(Closure $save): void
    {
        try {
            DB::transaction($save);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => __('validation.unique', ['attribute' => 'name'])]);
        }
    }
}
