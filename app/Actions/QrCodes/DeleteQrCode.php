<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use RuntimeException;

class DeleteQrCode
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(Request $request, QrCode $qrCode): void
    {
        $this->gate->forUser($request->user())->authorize('delete', $qrCode);

        if (! $qrCode->delete()) {
            throw new RuntimeException('Unable to delete the QR Code.');
        }
    }
}
