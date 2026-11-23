<?php

namespace App\Http\Controllers;

use App\Services\QrCodeGenerator;
use App\Support\QrTarget;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QrCodeDownloadController extends Controller
{
    /**
     * Owner-only (admin route group). Downloads the QR code as SVG (print) or PNG.
     */
    public function __invoke(Request $request, QrCodeGenerator $generator): Response
    {
        $data = $request->validate([
            'target' => ['required', Rule::in(array_keys(QrTarget::options()))],
            'project' => ['nullable', 'integer'],
            'format' => ['required', Rule::in(['svg', 'png'])],
            'track' => ['nullable', 'boolean'],
        ]);

        $url = QrTarget::url($data['target'], isset($data['project']) ? (int) $data['project'] : null, $request->boolean('track', true));
        abort_if($url === null, 404);

        $isSvg = $data['format'] === 'svg';
        $filename = 'qr-'.Str::slug($data['target']).($data['target'] === 'project' ? '-'.$data['project'] : '').'.'.$data['format'];

        return response($isSvg ? $generator->svg($url) : $generator->png($url, 16), 200, [
            'Content-Type' => $isSvg ? 'image/svg+xml' : 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
