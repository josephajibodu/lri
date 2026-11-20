<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * A QR code for a tracked link, so posters and flyers are credited like any other place the link is shared.
 */
class LinkQrCode
{
    /**
     * As SVG markup, to show in the admin.
     */
    public static function svg(string $url): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
        ])))->render($url);
    }

    /**
     * As PNG bytes, large enough to print on a poster.
     */
    public static function png(string $url): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => 20,
        ])))->render($url);
    }
}
