<?php

namespace App\Services\QrCodes;

use App\Models\QrCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class QrCodeLogoStorage
{
    private const DIRECTORY = 'qr-logos';

    public function save(QrCode $qrCode, ?UploadedFile $upload = null, bool $remove = false): QrCode
    {
        $previous = $qrCode->logo_path;
        $stored = $upload ? $this->store($upload) : null;

        if ($stored) {
            $qrCode->logo_path = $stored;
        } elseif ($remove) {
            $qrCode->logo_path = null;
        }

        try {
            if (! $qrCode->save()) {
                throw new RuntimeException('Unable to save the QR Code.');
            }
        } catch (Throwable $exception) {
            $this->delete([$stored]);

            throw $exception;
        }

        if ($previous !== $qrCode->logo_path) {
            $this->delete([$previous]);
        }

        return $qrCode;
    }

    public function contents(QrCode $qrCode): ?string
    {
        if (! $qrCode->hasLogo() || ! Storage::exists($qrCode->logo_path)) {
            return null;
        }

        return Storage::get($qrCode->logo_path);
    }

    public function pathsOf(Builder $qrCodes): array
    {
        return $qrCodes->whereNotNull('logo_path')->pluck('logo_path')->all();
    }

    public function delete(array $paths): void
    {
        $paths = array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));

        if ($paths !== []) {
            Storage::delete($paths);
        }
    }

    private function store(UploadedFile $upload): string
    {
        $path = $upload->store(self::DIRECTORY);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store the QR Code logo.');
        }

        return $path;
    }
}
