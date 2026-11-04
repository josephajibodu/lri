<?php

namespace App\Support;

use App\Models\Ticket;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * A ticket's QR code. It holds the headset desk's link with the ticket's code searched, so desk staff scan it with
 * their phone's camera and land on that ticket. The printed code goes underneath, for when a scan fails.
 */
class TicketQrCode
{
    /**
     * As SVG markup, for web pages.
     */
    public static function svg(Ticket $ticket): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
        ])))->render($ticket->deskUrl());
    }

    /**
     * As PNG bytes, for emails: Gmail and others don't show SVG.
     */
    public static function png(Ticket $ticket): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => 8,
        ])))->render($ticket->deskUrl());
    }
}
