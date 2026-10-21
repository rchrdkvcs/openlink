<?php

namespace App\Services\QrCodes\Readability;

use chillerlan\QRCode\Common\GDLuminanceSource;
use chillerlan\QRCode\QRCode as Reader;
use chillerlan\QRCode\QRCodeException;
use GdImage;

class QrDecoder
{
    public function available(): bool
    {
        return function_exists('imagecreatetruecolor') && class_exists(Reader::class);
    }

    public function decode(GdImage $image): ?string
    {
        try {
            return (string) (new Reader)->readFromSource(new GDLuminanceSource($image))->data;
        } catch (QRCodeException) {
            return null;
        }
    }
}
