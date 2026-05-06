<?php
declare(strict_types=1);

namespace Nightingale;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class QR
{
    /** Render a TOTP provisioning URI as a self-contained SVG string. */
    public static function svg(string $uri, int $size = 240): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        return $writer->writeString($uri, 'utf-8');
    }
}
