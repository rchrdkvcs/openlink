<?php

namespace App\Http\Controllers;

use App\Actions\Diagnostics\InstanceDiagnostics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InstanceDiagnosticsController extends Controller
{
    public function show(InstanceDiagnostics $diagnostics): Response
    {
        return Inertia::render('Settings/Diagnostics', [
            ...$diagnostics->report(),
            'canSendTestMail' => $diagnostics->canSendTestMail(),
        ]);
    }

    public function recheck(InstanceDiagnostics $diagnostics): RedirectResponse
    {
        $diagnostics->recheck();

        return back();
    }

    public function testMail(Request $request, InstanceDiagnostics $diagnostics): RedirectResponse
    {
        if (! $diagnostics->canSendTestMail()) {
            throw ValidationException::withMessages([
                'mail' => 'Configure a mail transport before sending a test email.',
            ]);
        }

        if (! $diagnostics->sendTestMail($request->user())) {
            throw ValidationException::withMessages([
                'mail' => 'The mail server did not accept the test email. Details are in the application log.',
            ]);
        }

        return back();
    }
}
