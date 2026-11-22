<?php

namespace App\Support;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR codes for certificates and receipts (bacon/bacon-qr-code, already
 * installed for Fortify's 2FA setup).
 */
final class QrCode
{
    /**
     * Inline SVG for web pages.
     */
    public static function svg(string $data, int $size = 192): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        // Drop the XML prolog so the markup can be inlined in HTML.
        return trim((string) preg_replace('/^<\?xml.*?\?>/', '', $writer->writeString($data)));
    }

    /**
     * PNG data URI for PDFs (DomPDF renders raster images most reliably).
     */
    public static function pngDataUri(string $data, int $size = 240): string
    {
        $writer = new Writer(new GDLibRenderer($size, 1));

        return 'data:image/png;base64,'.base64_encode($writer->writeString($data));
    }
}
