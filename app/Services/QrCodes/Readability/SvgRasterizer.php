<?php

namespace App\Services\QrCodes\Readability;

use DOMDocument;
use DOMElement;
use GdImage;

class SvgRasterizer
{
    private const ATTRIBUTES = [
        'svg' => ['xmlns', 'width', 'height', 'viewBox', 'shape-rendering'],
        'rect' => ['x', 'y', 'width', 'height', 'rx', 'fill'],
        'path' => ['d', 'fill', 'fill-rule'],
        'image' => ['x', 'y', 'width', 'height', 'href', 'preserveAspectRatio'],
    ];

    private const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    private const ARC_STEP = M_PI / 16;

    public function rasterize(string $svg, int $size): GdImage
    {
        $root = $this->root($svg);
        $scale = $size / $this->viewBoxSize($root);
        $image = imagecreatetruecolor($size, $size);
        imagefilledrectangle($image, 0, 0, $size - 1, $size - 1, imagecolorallocate($image, 255, 255, 255));
        imagealphablending($image, true);

        foreach ($root->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                throw new UnsupportedImage('Unexpected SVG content.');
            }

            $this->assertAttributes($node);

            match ($node->tagName) {
                'rect' => $this->fill($image, [$this->rectPoints($node)], 'nonzero', $this->color($node), $scale),
                'path' => $this->fill($image, $this->pathPoints($node->getAttribute('d')), $node->getAttribute('fill-rule') ?: 'nonzero', $this->color($node), $scale),
                'image' => $this->paintImage($image, $node, $scale),
                default => throw new UnsupportedImage('Unsupported SVG element <'.$node->tagName.'>.'),
            };
        }

        return $image;
    }

    private function root(string $svg): DOMElement
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $loaded ? $document->documentElement : null;

        if (! $root instanceof DOMElement || $root->tagName !== 'svg') {
            throw new UnsupportedImage('The SVG could not be parsed.');
        }

        $this->assertAttributes($root);

        return $root;
    }

    private function viewBoxSize(DOMElement $root): float
    {
        $box = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox')));

        if (count($box) !== 4 || (float) $box[0] !== 0.0 || (float) $box[1] !== 0.0 || (float) $box[2] <= 0 || (float) $box[2] !== (float) $box[3]) {
            throw new UnsupportedImage('The SVG viewBox must be a square starting at the origin.');
        }

        return (float) $box[2];
    }

    private function assertAttributes(DOMElement $element): void
    {
        $allowed = self::ATTRIBUTES[$element->tagName] ?? [];

        foreach ($element->attributes as $attribute) {
            if (! in_array($attribute->nodeName, $allowed, true)) {
                throw new UnsupportedImage('Unsupported SVG attribute '.$attribute->nodeName.'.');
            }
        }
    }

    private function color(DOMElement $element): array
    {
        if (! preg_match('/^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/', $element->getAttribute('fill'), $match)) {
            throw new UnsupportedImage('Unsupported SVG fill.');
        }

        return [hexdec($match[1]), hexdec($match[2]), hexdec($match[3])];
    }

    private function rectPoints(DOMElement $rect): array
    {
        $x = (float) $rect->getAttribute('x');
        $y = (float) $rect->getAttribute('y');
        $w = (float) $rect->getAttribute('width');
        $h = (float) $rect->getAttribute('height');
        $r = min((float) $rect->getAttribute('rx'), $w / 2, $h / 2);

        if ($r <= 0) {
            return [[$x, $y], [$x + $w, $y], [$x + $w, $y + $h], [$x, $y + $h]];
        }

        return [
            [$x + $r, $y],
            [$x + $w - $r, $y],
            ...$this->arc($x + $w - $r, $y, $r, $r, 0, 0, 1, $x + $w, $y + $r),
            [$x + $w, $y + $h - $r],
            ...$this->arc($x + $w, $y + $h - $r, $r, $r, 0, 0, 1, $x + $w - $r, $y + $h),
            [$x + $r, $y + $h],
            ...$this->arc($x + $r, $y + $h, $r, $r, 0, 0, 1, $x, $y + $h - $r),
            [$x, $y + $r],
            ...$this->arc($x, $y + $r, $r, $r, 0, 0, 1, $x + $r, $y),
        ];
    }

    private function pathPoints(string $d): array
    {
        preg_match_all('/[A-Za-z]|[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?/', $d, $matches);
        $tokens = $matches[0];
        $count = count($tokens);
        $subpaths = [];
        $points = [];
        $x = $y = $startX = $startY = 0.0;
        $command = null;
        $i = 0;

        $number = function () use (&$i, $tokens, $count): float {
            if ($i >= $count || ! is_numeric($tokens[$i])) {
                throw new UnsupportedImage('Malformed SVG path data.');
            }

            return (float) $tokens[$i++];
        };

        while ($i < $count) {
            if (! is_numeric($tokens[$i])) {
                $command = $tokens[$i++];
            } elseif ($command === null || in_array($command, ['Z', 'z'], true)) {
                throw new UnsupportedImage('Malformed SVG path data.');
            }

            $relative = ctype_lower((string) $command);

            switch (strtoupper((string) $command)) {
                case 'M':
                    if (count($points) > 2) {
                        $subpaths[] = $points;
                    }
                    $x = $number() + ($relative ? $x : 0);
                    $y = $number() + ($relative ? $y : 0);
                    [$startX, $startY] = [$x, $y];
                    $points = [[$x, $y]];
                    $command = $relative ? 'l' : 'L';
                    break;
                case 'L':
                    $x = $number() + ($relative ? $x : 0);
                    $y = $number() + ($relative ? $y : 0);
                    $points[] = [$x, $y];
                    break;
                case 'H':
                    $x = $number() + ($relative ? $x : 0);
                    $points[] = [$x, $y];
                    break;
                case 'V':
                    $y = $number() + ($relative ? $y : 0);
                    $points[] = [$x, $y];
                    break;
                case 'A':
                    [$rx, $ry, $rotation, $large, $sweep] = [$number(), $number(), $number(), $number(), $number()];
                    $toX = $number() + ($relative ? $x : 0);
                    $toY = $number() + ($relative ? $y : 0);
                    array_push($points, ...$this->arc($x, $y, $rx, $ry, $rotation, (int) $large, (int) $sweep, $toX, $toY));
                    [$x, $y] = [$toX, $toY];
                    break;
                case 'Z':
                    if (count($points) > 2) {
                        $subpaths[] = $points;
                    }
                    [$x, $y] = [$startX, $startY];
                    $points = [[$x, $y]];
                    break;
                default:
                    throw new UnsupportedImage('Unsupported SVG path command '.$command.'.');
            }
        }

        if (count($points) > 2) {
            $subpaths[] = $points;
        }

        return $subpaths;
    }

    private function arc(float $x1, float $y1, float $rx, float $ry, float $rotation, int $large, int $sweep, float $x2, float $y2): array
    {
        $rx = abs($rx);
        $ry = abs($ry);

        if ($rx == 0.0 || $ry == 0.0 || ($x1 === $x2 && $y1 === $y2)) {
            return [[$x2, $y2]];
        }

        $phi = deg2rad($rotation);
        [$cos, $sin] = [cos($phi), sin($phi)];
        $dx = ($x1 - $x2) / 2;
        $dy = ($y1 - $y2) / 2;
        $x1p = $cos * $dx + $sin * $dy;
        $y1p = -$sin * $dx + $cos * $dy;

        $lambda = ($x1p ** 2) / ($rx ** 2) + ($y1p ** 2) / ($ry ** 2);

        if ($lambda > 1) {
            $rx *= sqrt($lambda);
            $ry *= sqrt($lambda);
        }

        $numerator = $rx ** 2 * $ry ** 2 - $rx ** 2 * $y1p ** 2 - $ry ** 2 * $x1p ** 2;
        $denominator = $rx ** 2 * $y1p ** 2 + $ry ** 2 * $x1p ** 2;
        $coefficient = sqrt(max(0, $numerator / $denominator)) * ($large === $sweep ? -1 : 1);
        $cxp = $coefficient * $rx * $y1p / $ry;
        $cyp = -$coefficient * $ry * $x1p / $rx;
        $cx = $cos * $cxp - $sin * $cyp + ($x1 + $x2) / 2;
        $cy = $sin * $cxp + $cos * $cyp + ($y1 + $y2) / 2;

        $start = atan2(($y1p - $cyp) / $ry, ($x1p - $cxp) / $rx);
        $delta = atan2((-$y1p - $cyp) / $ry, (-$x1p - $cxp) / $rx) - $start;

        if ($sweep === 0 && $delta > 0) {
            $delta -= 2 * M_PI;
        } elseif ($sweep === 1 && $delta < 0) {
            $delta += 2 * M_PI;
        }

        $steps = max(2, (int) ceil(abs($delta) / self::ARC_STEP));
        $points = [];

        for ($step = 1; $step < $steps; $step++) {
            $angle = $start + $delta * $step / $steps;
            $points[] = [
                $cos * $rx * cos($angle) - $sin * $ry * sin($angle) + $cx,
                $sin * $rx * cos($angle) + $cos * $ry * sin($angle) + $cy,
            ];
        }

        $points[] = [$x2, $y2];

        return $points;
    }

    private function fill(GdImage $image, array $subpaths, string $rule, array $rgb, float $scale): void
    {
        if (! in_array($rule, ['nonzero', 'evenodd'], true)) {
            throw new UnsupportedImage('Unsupported SVG fill rule.');
        }

        $height = imagesy($image);
        $width = imagesx($image);
        $color = imagecolorallocate($image, ...$rgb);
        $rows = [];

        foreach ($subpaths as $points) {
            $total = count($points);

            for ($index = 0; $index < $total; $index++) {
                [$ax, $ay] = $points[$index];
                [$bx, $by] = $points[($index + 1) % $total];
                [$ax, $ay, $bx, $by] = [$ax * $scale, $ay * $scale, $bx * $scale, $by * $scale];

                if ($ay == $by) {
                    continue;
                }

                $direction = $by > $ay ? 1 : -1;
                [$top, $bottom] = $ay < $by ? [$ay, $by] : [$by, $ay];
                $first = max(0, (int) ceil($top - 0.5));
                $last = min($height - 1, (int) ceil($bottom - 0.5) - 1);

                for ($row = $first; $row <= $last; $row++) {
                    $rows[$row][] = [$ax + ($row + 0.5 - $ay) * ($bx - $ax) / ($by - $ay), $direction];
                }
            }
        }

        foreach ($rows as $row => $crossings) {
            usort($crossings, fn (array $a, array $b) => $a[0] <=> $b[0]);
            $winding = 0;
            $count = count($crossings);

            for ($index = 0; $index < $count - 1; $index++) {
                $winding += $rule === 'evenodd' ? 1 : $crossings[$index][1];
                $inside = $rule === 'evenodd' ? $winding % 2 === 1 : $winding !== 0;

                if (! $inside) {
                    continue;
                }

                $from = max(0, (int) ceil($crossings[$index][0] - 0.5));
                $to = min($width - 1, (int) ceil($crossings[$index + 1][0] - 0.5) - 1);

                if ($to >= $from) {
                    imagefilledrectangle($image, $from, $row, $to, $row, $color);
                }
            }
        }
    }

    private function paintImage(GdImage $image, DOMElement $node, float $scale): void
    {
        if (! in_array($node->getAttribute('preserveAspectRatio'), ['', 'xMidYMid meet'], true)
            || ! preg_match('/^data:([a-z\/]+);base64,([A-Za-z0-9+\/=]+)$/', $node->getAttribute('href'), $match)
            || ! in_array($match[1], self::IMAGE_TYPES, true)) {
            throw new UnsupportedImage('Unsupported embedded SVG image.');
        }

        $source = @imagecreatefromstring((string) base64_decode($match[2], true));

        if (! $source) {
            throw new UnsupportedImage('The embedded SVG image could not be decoded.');
        }

        $boxX = (float) $node->getAttribute('x') * $scale;
        $boxY = (float) $node->getAttribute('y') * $scale;
        $boxW = (float) $node->getAttribute('width') * $scale;
        $boxH = (float) $node->getAttribute('height') * $scale;
        $sourceW = imagesx($source);
        $sourceH = imagesy($source);
        $fit = min($boxW / $sourceW, $boxH / $sourceH);
        $targetW = max(1, (int) round($sourceW * $fit));
        $targetH = max(1, (int) round($sourceH * $fit));

        imagecopyresampled(
            $image,
            $source,
            (int) round($boxX + ($boxW - $targetW) / 2),
            (int) round($boxY + ($boxH - $targetH) / 2),
            0,
            0,
            $targetW,
            $targetH,
            $sourceW,
            $sourceH,
        );
    }
}
