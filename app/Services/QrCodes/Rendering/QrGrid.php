<?php

namespace App\Services\QrCodes\Rendering;

use App\Models\QrCode;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

final class QrGrid
{
    public const FINDER_SIZE = 7;

    public const LOGO_RATIO = 0.22;

    private function __construct(
        public readonly array $modules,
        public readonly int $count,
        public readonly int $size,
        public readonly int $margin,
        public readonly float $moduleSize,
        public readonly float $offset,
    ) {}

    public static function for(QrCode $qrCode, string $content, ?int $size = null): self
    {
        $matrix = Encoder::encode($content, self::level($qrCode), 'UTF-8')->getMatrix();
        $modules = [];

        for ($row = 0; $row < $matrix->getHeight(); $row++) {
            for ($column = 0; $column < $matrix->getWidth(); $column++) {
                $modules[$row][$column] = $matrix->get($column, $row) === 1 ? 1 : 0;
            }
        }

        $count = $matrix->getWidth();
        $size = $size ?? (int) $qrCode->size;
        $margin = (int) $qrCode->margin;
        $total = $count + 2 * $margin;
        $moduleSize = $total > 0 ? $size / $total : 1;

        return new self($modules, $count, $size, $margin, $moduleSize, $margin * $moduleSize);
    }

    public function isDark(int $row, int $column): bool
    {
        return ($this->modules[$row][$column] ?? 0) === 1;
    }

    public function dataModules(): iterable
    {
        foreach ($this->modules as $row => $columns) {
            foreach ($columns as $column => $value) {
                if ($value && ! $this->inFinder($row, $column)) {
                    yield [$row, $column];
                }
            }
        }
    }

    public function finderPositions(): array
    {
        return array_map(
            fn (array $origin) => [$this->offset + $origin[1] * $this->moduleSize, $this->offset + $origin[0] * $this->moduleSize],
            $this->finderOrigins(),
        );
    }

    public function x(float $column): float
    {
        return $this->offset + $column * $this->moduleSize;
    }

    public function y(float $row): float
    {
        return $this->offset + $row * $this->moduleSize;
    }

    private function finderOrigins(): array
    {
        $far = $this->count - self::FINDER_SIZE;

        return [[0, 0], [0, $far], [$far, 0]];
    }

    private function inFinder(int $row, int $column): bool
    {
        foreach ($this->finderOrigins() as [$fRow, $fColumn]) {
            if ($row >= $fRow && $row < $fRow + self::FINDER_SIZE && $column >= $fColumn && $column < $fColumn + self::FINDER_SIZE) {
                return true;
            }
        }

        return false;
    }

    private static function level(QrCode $qrCode): ErrorCorrectionLevel
    {
        $level = $qrCode->error_correction;

        if ($qrCode->hasLogo() && in_array($level, ['low', 'medium'], true)) {
            $level = 'quartile';
        }

        return match ($level) {
            'low' => ErrorCorrectionLevel::L(),
            'quartile' => ErrorCorrectionLevel::Q(),
            'high' => ErrorCorrectionLevel::H(),
            default => ErrorCorrectionLevel::M(),
        };
    }
}
