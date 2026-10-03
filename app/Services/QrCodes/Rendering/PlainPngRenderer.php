<?php

namespace App\Services\QrCodes\Rendering;

use App\Models\QrCode;

class PlainPngRenderer
{
    public function render(QrCode $qrCode, QrGrid $grid): string
    {
        $size = $grid->size;
        $fg = $this->rgba($qrCode->foreground_color, 255);
        $bg = $this->rgba($qrCode->background_color, $qrCode->background_transparent ? 0 : 255);
        $raw = '';

        for ($y = 0; $y < $size; $y++) {
            $raw .= "\x00";
            $row = (int) floor($y / $grid->moduleSize) - $grid->margin;

            for ($x = 0; $x < $size; $x++) {
                $column = (int) floor($x / $grid->moduleSize) - $grid->margin;
                $raw .= $grid->isDark($row, $column) ? $fg : $bg;
            }
        }

        return "\x89PNG\r\n\x1a\n"
            .$this->chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 6, 0, 0, 0))
            .$this->chunk('IDAT', gzcompress($raw))
            .$this->chunk('IEND', '');
    }

    private function rgba(string $hex, int $alpha): string
    {
        $hex = ltrim($hex, '#');

        return chr((int) hexdec(substr($hex, 0, 2)))
            .chr((int) hexdec(substr($hex, 2, 2)))
            .chr((int) hexdec(substr($hex, 4, 2)))
            .chr($alpha);
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
