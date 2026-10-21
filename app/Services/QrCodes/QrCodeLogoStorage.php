<?php

namespace App\Services\QrCodes;

use App\Models\QrCode;
use App\Models\QrCodeTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class QrCodeLogoStorage
{
    private const DIRECTORY = 'qr-logos';

    public function save(QrCode|QrCodeTemplate $owner, ?UploadedFile $upload = null, bool $remove = false): QrCode|QrCodeTemplate
    {
        return $this->persist($owner, $upload ? $this->store($upload) : null, $remove);
    }

    public function saveCopy(QrCode|QrCodeTemplate $owner, ?string $source): QrCode|QrCodeTemplate
    {
        return $this->persist($owner, $this->copy($source), true);
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

    private function persist(QrCode|QrCodeTemplate $owner, ?string $stored, bool $remove): QrCode|QrCodeTemplate
    {
        $previous = $owner->logo_path;

        if ($stored) {
            $owner->logo_path = $stored;
        } elseif ($remove) {
            $owner->logo_path = null;
        }

        try {
            if (! $owner->save()) {
                throw new RuntimeException('Unable to save the QR Code design.');
            }
        } catch (Throwable $exception) {
            $this->delete([$stored]);

            throw $exception;
        }

        if ($previous !== $owner->logo_path) {
            $this->delete([$previous]);
        }

        return $owner;
    }

    private function copy(?string $source): ?string
    {
        if (! is_string($source) || $source === '' || ! Storage::exists($source)) {
            return null;
        }

        $path = self::DIRECTORY.'/'.Str::random(40).'.'.(pathinfo($source, PATHINFO_EXTENSION) ?: 'png');

        if (! Storage::copy($source, $path)) {
            throw new RuntimeException('Unable to copy the QR Code logo.');
        }

        return $path;
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
