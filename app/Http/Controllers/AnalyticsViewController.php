<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Requests\Analytics\AnalyticsViewRequest;
use App\Models\AnalyticsView;
use App\Services\Analytics\AnalyticsFilterTargets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AnalyticsViewController extends Controller
{
    public function store(AnalyticsViewRequest $request, CurrentWorkspace $current, AnalyticsFilterTargets $targets): RedirectResponse
    {
        $workspace = $current->require();
        $filters = $request->filters();
        $unavailable = $targets->unavailable($workspace, $filters);

        if ($unavailable !== []) {
            throw ValidationException::withMessages(collect($unavailable)
                ->mapWithKeys(fn (string $key) => ["filters.{$key}" => 'This filter is not available in this workspace.'])
                ->all());
        }

        $view = AnalyticsView::create([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'filters' => $filters->toQuery(),
        ]);

        return redirect()->route('analytics.index', ['view' => $view->id]);
    }

    public function update(AnalyticsViewRequest $request, AnalyticsView $analyticsView): RedirectResponse
    {
        Gate::authorize('update', $analyticsView);

        $analyticsView->update(['name' => $request->validated('name')]);

        return redirect()->route('analytics.index', ['view' => $analyticsView->id]);
    }

    public function destroy(AnalyticsView $analyticsView): RedirectResponse
    {
        Gate::authorize('delete', $analyticsView);

        $analyticsView->delete();

        return redirect()->route('analytics.index', $analyticsView->filters);
    }
}
