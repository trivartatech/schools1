<style>
    @page { margin: 5mm 8mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 13px; color: #333; margin: 0; padding: 0; }
    /* Never break a copy across pages */
    .copy-page { page-break-inside: avoid; }

    /* Header — logo on the left, school text right next to it (no centering wide-empty) */
    .header { border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 12px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .header-logo-cell { width: 80px; vertical-align: middle; padding: 0; text-align: left; }
    .header-text-cell  { vertical-align: middle; padding: 0 0 0 8px; text-align: left; }
    .school-description { font-size: 10px; color: #666; font-style: italic; margin-bottom: 1px; letter-spacing: 0.3px; }
    .school-name { font-size: 20px; font-weight: bold; margin: 0; text-transform: uppercase; line-height: 1.1; }
    .school-address { font-size: 10px; color: #666; margin-top: 2px; line-height: 1.3; }
    .school-logo { height: 70px; }

    /* Receipt body */
    .receipt-title { text-align: center; font-size: 15px; font-weight: bold; margin: 8px 0; text-decoration: underline; }
    .info-table, .details-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .info-table td { padding: 3px 4px; vertical-align: top; }
    .label { font-weight: bold; color: #555; width: 22%; }
    .details-table th, .details-table td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
    .details-table th { background-color: #f8f9fa; font-weight: bold; }
    .details-table .text-right { text-align: right; }
    .total-row th, .total-row td { font-weight: bold; background-color: #f8f9fa; }
    .footer { margin-top: 15px; display: table; width: 100%; }
    .signature-box { display: table-cell; width: 50%; text-align: right; vertical-align: bottom; }
    .signature-line { display: inline-block; width: 180px; border-top: 1px solid #333; text-align: center; padding-top: 4px; }
    .qr-box { display: table-cell; width: 50%; vertical-align: bottom; }
    .qr-code { width: 80px; height: 80px; }
    .qr-text { font-size: 9px; color: #777; margin-top: 4px; }

    /* Copy label (Original / Duplicate / etc) */
    .copy-label-bar { text-align: right; margin-bottom: 3px; }
    .copy-label { display: inline-block; font-size: 10px; font-weight: bold; color: #555; border: 1px solid #999; padding: 2px 8px; border-radius: 3px; letter-spacing: 0.5px; text-transform: uppercase; }

    /* Cut line between stacked copies */
    .cut-line { text-align: center; border-top: 1px dashed #999; margin: 0; padding-top: 3px; color: #888; font-size: 10px; letter-spacing: 2px; }

    /* ─────────────────────────────────────────────────────────
       Multi-copy modes — each copy occupies a fixed slice of A4
       so the cut line lands exactly at the half/third/quarter mark
       ───────────────────────────────────────────────────────── */
    /* 2 copies — each copy is half an A4 (≈135mm) */
    body.copies-2 { font-size: 10px; }
    body.copies-2 .copy-page { height: 135mm; box-sizing: border-box; padding-bottom: 3mm; overflow: hidden; }
    body.copies-2 .school-description { font-size: 8px; }
    body.copies-2 .school-name { font-size: 15px; }
    body.copies-2 .school-address { font-size: 8.5px; margin-top: 1px; }
    body.copies-2 .school-logo { height: 42px; }
    body.copies-2 .header-logo-cell { width: 50px; }
    body.copies-2 .header { padding-bottom: 4px; margin-bottom: 6px; }
    body.copies-2 .receipt-title { font-size: 12px; margin: 4px 0; }
    body.copies-2 .info-table { margin-bottom: 5px; }
    body.copies-2 .info-table td { padding: 1px 3px; font-size: 9px; }
    body.copies-2 .details-table { margin-bottom: 4px; }
    body.copies-2 .details-table th, body.copies-2 .details-table td { padding: 3px 5px; font-size: 9px; }
    body.copies-2 .footer { margin-top: 4px; }
    body.copies-2 .qr-code { width: 50px; height: 50px; }
    body.copies-2 .qr-text { font-size: 7px; }
    body.copies-2 .signature-line { width: 130px; padding-top: 2px; font-size: 9px; }
    body.copies-2 .copy-label { font-size: 8px; padding: 1px 5px; }

    /* 3 copies — each copy is one-third of A4 (≈88mm) */
    body.copies-3 { font-size: 8.5px; }
    body.copies-3 .copy-page { height: 88mm; box-sizing: border-box; padding-bottom: 2mm; overflow: hidden; }
    body.copies-3 .school-description { font-size: 7px; }
    body.copies-3 .school-name { font-size: 12px; }
    body.copies-3 .school-address { font-size: 7.5px; margin-top: 1px; }
    body.copies-3 .school-logo { height: 30px; }
    body.copies-3 .header-logo-cell { width: 38px; }
    body.copies-3 .header { padding-bottom: 3px; margin-bottom: 4px; }
    body.copies-3 .receipt-title { font-size: 10px; margin: 2px 0; }
    body.copies-3 .info-table { margin-bottom: 3px; }
    body.copies-3 .info-table td { padding: 1px 2px; font-size: 7.5px; }
    body.copies-3 .details-table { margin-bottom: 3px; }
    body.copies-3 .details-table th, body.copies-3 .details-table td { padding: 2px 4px; font-size: 7.5px; }
    body.copies-3 .footer { margin-top: 3px; }
    body.copies-3 .qr-code { width: 38px; height: 38px; }
    body.copies-3 .qr-text { font-size: 6px; }
    body.copies-3 .signature-line { width: 100px; padding-top: 1px; font-size: 7.5px; }
    body.copies-3 .copy-label { font-size: 7px; padding: 0 4px; }

    /* 4 copies — each copy is one-quarter of A4 (≈65mm) */
    body.copies-4 { font-size: 7.5px; }
    body.copies-4 .copy-page { height: 65mm; box-sizing: border-box; padding-bottom: 1.5mm; overflow: hidden; }
    body.copies-4 .school-description { font-size: 6px; }
    body.copies-4 .school-name { font-size: 11px; }
    body.copies-4 .school-address { font-size: 6.5px; margin-top: 0; }
    body.copies-4 .school-logo { height: 24px; }
    body.copies-4 .header-logo-cell { width: 32px; }
    body.copies-4 .header { padding-bottom: 2px; margin-bottom: 3px; }
    body.copies-4 .receipt-title { font-size: 9px; margin: 1px 0; }
    body.copies-4 .info-table { margin-bottom: 2px; }
    body.copies-4 .info-table td { padding: 0 2px; font-size: 7px; }
    body.copies-4 .label { width: 26%; }
    body.copies-4 .details-table { margin-bottom: 2px; }
    body.copies-4 .details-table th, body.copies-4 .details-table td { padding: 2px 3px; font-size: 7px; }
    body.copies-4 .footer { margin-top: 2px; }
    body.copies-4 .qr-code { width: 32px; height: 32px; }
    body.copies-4 .qr-text { font-size: 5.5px; }
    body.copies-4 .signature-line { width: 75px; padding-top: 1px; font-size: 6.5px; }
    body.copies-4 .copy-label { font-size: 6.5px; padding: 0 3px; }
</style>
