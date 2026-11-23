<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR codes for printed CVs and digital business cards. Always dark modules on a
 * white background with a quiet zone, which is what scanners read most reliably.
 */
class QrCodeGenerator
{
    public function svg(string $url): string
    {
        return (string) (new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'svgAddXmlHeader' => true,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'connectPaths' => true,
            'drawLightModules' => false,
            // Explicit fills: the SVG must look the same in dark-mode viewers and on paper.
            'svgDefs' => '<style><![CDATA[ .dark { fill: #111111; } ]]></style>',
            'bgColor' => '#ffffff',
        ])))->render($url);
    }

    public function png(string $url, int $scale = 10): string
    {
        return (string) (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'scale' => max(2, min($scale, 20)),
            'bgColor' => [255, 255, 255],
            'imageTransparent' => false,
        ])))->render($url);
    }

    public function pngDataUri(string $url, int $scale = 6): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($url, $scale));
    }
}
