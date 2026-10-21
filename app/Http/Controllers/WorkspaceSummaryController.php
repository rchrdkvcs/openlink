<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Requests\Analytics\UpdateWorkspaceSummaryRequest;
use App\Models\AnalyticsSummaryDelivery;
use App\Models\AnalyticsSummarySchedule;
use App\Models\Workspace;
use App\Services\Analytics\SummaryPeriod;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceSummaryController extends Controller
{
    public function show(CurrentWorkspace $current): Response
    {
        $workspace = $current->require('manage');
        $schedule = $workspace->analyticsSummarySchedule;

        return Inertia::render('Settings/WorkspaceSummary', [
            'schedule' => [
                'is_enabled' => (bool) $schedule?->is_enabled,
                'timezone' => $schedule?->timezone,
                'recipients' => $schedule?->recipients()->pluck('workspace_members.id')->all() ?? [],
            ],
            'members' => $workspace->members()
                ->with('user:id,name,email')
                ->orderBy('id')
                ->get()
                ->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'role' => $member->role,
                ]),
            'status' => $this->status($schedule),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(UpdateWorkspaceSummaryRequest $request, CurrentWorkspace $current): RedirectResponse
    {
        $workspace = $current->require('manage');
        $data = $request->validated();

        DB::transaction(function () use ($workspace, $data) {
            $schedule = AnalyticsSummarySchedule::query()->updateOrCreate(
                ['workspace_id' => $workspace->id],
                ['is_enabled' => $data['is_enabled'], 'timezone' => $data['timezone']],
            );
            $schedule->recipients()->sync($data['recipients']);
            $this->cancelUnwanted($workspace, $schedule);
        });

        return redirect()->route('settings.summaries');
    }

    private function cancelUnwanted(Workspace $workspace, AnalyticsSummarySchedule $schedule): void
    {
        $pending = $schedule->deliveries()->whereIn('status', [AnalyticsSummaryDelivery::STATUS_PENDING, AnalyticsSummaryDelivery::STATUS_FAILED]);

        if ($schedule->is_enabled) {
            $pending->whereNotIn('user_id', $schedule->recipients()->where('workspace_members.workspace_id', $workspace->id)->select('workspace_members.user_id'));
        }

        $pending->update(['status' => AnalyticsSummaryDelivery::STATUS_CANCELLED]);
    }

    private function status(?AnalyticsSummarySchedule $schedule): array
    {
        if (! $schedule) {
            return ['last_sent_at' => null, 'last_failure' => null, 'pending' => 0, 'next_send_at' => null];
        }

        $lastSent = $schedule->deliveries()->where('status', AnalyticsSummaryDelivery::STATUS_SENT)->max('sent_at');
        $failure = $schedule->deliveries()
            ->where('status', AnalyticsSummaryDelivery::STATUS_FAILED)
            ->latest('failed_at')
            ->first(['failed_at', 'failure']);

        return [
            'last_sent_at' => $lastSent ? CarbonImmutable::parse($lastSent)->toIso8601String() : null,
            'last_failure' => $failure ? ['at' => $failure->failed_at->toIso8601String(), 'message' => $failure->failure] : null,
            'pending' => $schedule->deliveries()->where('status', AnalyticsSummaryDelivery::STATUS_PENDING)->count(),
            'next_send_at' => $schedule->is_enabled
                ? SummaryPeriod::next($schedule->timezone, CarbonImmutable::now())->sendAt()->toIso8601String()
                : null,
        ];
    }
}
