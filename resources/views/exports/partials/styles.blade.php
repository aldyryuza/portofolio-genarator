{{-- Shared dompdf styles for every export template. Expects $theme. --}}
    <style>
        @page { margin: 18mm 16mm 20mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.5; color: {{ $theme['ink'] }}; background: {{ $theme['background'] }}; }
        h1, h2, h3 { font-family: 'DejaVu Serif', serif; font-weight: normal; margin: 0; line-height: 1.2; }
        h1 { font-size: 30pt; }
        h2 { font-size: 20pt; margin-bottom: 4mm; }
        h3 { font-size: 12pt; margin-top: 5mm; margin-bottom: 1.5mm; }
        p { margin: 0 0 2.5mm; }
        /* Break before each page after the first, so no blank trailing page is produced. */
        .page + .page { page-break-before: always; }
        .eyebrow { font-size: 7.5pt; letter-spacing: 1.5pt; text-transform: uppercase; color: {{ $theme['muted'] }}; margin-bottom: 2mm; }
        .muted { color: {{ $theme['muted'] }}; }
        .accent { color: {{ $theme['accent'] }}; }
        .rule { border-top: 1px solid {{ $theme['line'] }}; margin: 5mm 0; }
        .meta { width: 100%; border-collapse: collapse; margin: 4mm 0; }
        .meta td { vertical-align: top; padding: 2mm 3mm 2mm 0; border-top: 1px solid {{ $theme['line'] }}; font-size: 9pt; }
        .meta .label { width: 28mm; font-size: 7.5pt; letter-spacing: 1pt; text-transform: uppercase; color: {{ $theme['muted'] }}; }
        .chip { display: inline-block; border: 1px solid {{ $theme['line'] }}; border-radius: 8pt; padding: 0.5mm 2mm; margin: 0 1mm 1mm 0; font-size: 8pt; }
        .cover-image { width: 100%; border: 1px solid {{ $theme['line'] }}; margin: 3mm 0 4mm; }
        .gallery { width: 100%; border-collapse: separate; border-spacing: 2mm; margin-left: -2mm; }
        .gallery td { width: 50%; vertical-align: top; }
        .gallery img { width: 100%; border: 1px solid {{ $theme['line'] }}; }
        .no-break { page-break-inside: avoid; }
        .timeline td { vertical-align: top; padding: 0 0 5mm; }
        .timeline .when { width: 34mm; padding-right: 4mm; font-size: 8.5pt; color: {{ $theme['muted'] }}; }
        .highlight { border-left: 2pt solid {{ $theme['accent'] }}; padding-left: 3mm; }
        .avatar { width: 34mm; height: 34mm; border: 1px solid {{ $theme['line'] }}; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: {{ $theme['muted'] }}; border-top: 1px solid {{ $theme['line'] }}; padding-top: 2mm; }
        .footer .page-number:after { content: counter(page); }
        ul { margin: 0 0 3mm; padding-left: 5mm; }
        li { margin-bottom: 1mm; }
    </style>
