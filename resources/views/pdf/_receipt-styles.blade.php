<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 13px; color: #333; margin: 0; padding: 10px; }

    /* Header — logo on the left, school text to its right */
    .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .header-logo-cell { width: 90px; vertical-align: middle; padding: 0; text-align: center; }
    .header-text-cell  { vertical-align: middle; padding: 0 0 0 10px; text-align: center; }
    .school-name { font-size: 22px; font-weight: bold; margin: 0; text-transform: uppercase; }
    .school-address { font-size: 11px; color: #666; margin-top: 3px; }
    .school-logo { height: 60px; }

    /* Receipt body */
    .receipt-title { text-align: center; font-size: 16px; font-weight: bold; margin: 10px 0; text-decoration: underline; }
    .info-table, .details-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    .info-table td { padding: 4px; vertical-align: top; }
    .label { font-weight: bold; color: #555; width: 22%; }
    .details-table th, .details-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    .details-table th { background-color: #f8f9fa; font-weight: bold; }
    .details-table .text-right { text-align: right; }
    .total-row th, .total-row td { font-weight: bold; background-color: #f8f9fa; }
    .footer { margin-top: 25px; display: table; width: 100%; }
    .signature-box { display: table-cell; width: 50%; text-align: right; vertical-align: bottom; }
    .signature-line { display: inline-block; width: 200px; border-top: 1px solid #333; text-align: center; padding-top: 5px; }
    .qr-box { display: table-cell; width: 50%; vertical-align: bottom; }
    .qr-code { width: 90px; height: 90px; }
    .qr-text { font-size: 10px; color: #777; margin-top: 5px; }

    /* Copy label (Original / Duplicate / etc) */
    .copy-label-bar { text-align: right; margin-bottom: 4px; }
    .copy-label { display: inline-block; font-size: 10px; font-weight: bold; color: #555; border: 1px solid #999; padding: 2px 8px; border-radius: 3px; letter-spacing: 0.5px; text-transform: uppercase; }

    /* Cut line between stacked copies */
    .cut-line { text-align: center; border-top: 1px dashed #999; margin: 8px 0; padding-top: 3px; color: #888; font-size: 10px; letter-spacing: 2px; }

    /* ─────────────────────────────────────────────────────────
       Multi-copy modes — ensure everything fits on a single page
       ───────────────────────────────────────────────────────── */
    /* 2 copies: stacked vertically with compact CSS — fits A4 easily */
    body.copies-2 { font-size: 10px; padding: 5px; }
    body.copies-2 .school-name { font-size: 16px; }
    body.copies-2 .school-address { font-size: 9px; margin-top: 2px; }
    body.copies-2 .school-logo { height: 38px; }
    body.copies-2 .header-logo-cell { width: 60px; }
    body.copies-2 .header { padding-bottom: 6px; margin-bottom: 8px; }
    body.copies-2 .receipt-title { font-size: 12px; margin: 5px 0; }
    body.copies-2 .info-table { margin-bottom: 6px; }
    body.copies-2 .info-table td { padding: 1px 4px; }
    body.copies-2 .details-table { margin-bottom: 6px; }
    body.copies-2 .details-table th, body.copies-2 .details-table td { padding: 4px 6px; }
    body.copies-2 .footer { margin-top: 8px; }
    body.copies-2 .qr-code { width: 60px; height: 60px; }
    body.copies-2 .qr-text { font-size: 8px; }
    body.copies-2 .signature-line { width: 140px; padding-top: 3px; }
    body.copies-2 .copy-label { font-size: 9px; padding: 1px 6px; }

    /* 3 copies: still vertical stack, much more compact so it fits A4 */
    body.copies-3 { font-size: 8.5px; padding: 4px; }
    body.copies-3 .school-name { font-size: 13px; }
    body.copies-3 .school-address { font-size: 8px; margin-top: 1px; }
    body.copies-3 .school-logo { height: 28px; }
    body.copies-3 .header-logo-cell { width: 45px; }
    body.copies-3 .header { padding-bottom: 4px; margin-bottom: 5px; }
    body.copies-3 .receipt-title { font-size: 10px; margin: 3px 0; }
    body.copies-3 .info-table { margin-bottom: 4px; }
    body.copies-3 .info-table td { padding: 1px 3px; font-size: 8.5px; }
    body.copies-3 .details-table { margin-bottom: 4px; }
    body.copies-3 .details-table th, body.copies-3 .details-table td { padding: 3px 5px; font-size: 8.5px; }
    body.copies-3 .footer { margin-top: 6px; }
    body.copies-3 .qr-code { width: 48px; height: 48px; }
    body.copies-3 .qr-text { font-size: 7px; }
    body.copies-3 .signature-line { width: 110px; padding-top: 2px; }
    body.copies-3 .copy-label { font-size: 8px; padding: 1px 5px; }
    body.copies-3 .cut-line { margin: 5px 0; }

    /* 4 copies: 2x2 side-by-side grid (each cell ~half width × half height of A4) */
    body.copies-4 { font-size: 8px; padding: 3px; }
    body.copies-4 .school-name { font-size: 12px; }
    body.copies-4 .school-address { font-size: 7px; margin-top: 1px; }
    body.copies-4 .school-logo { height: 24px; }
    body.copies-4 .header-logo-cell { width: 38px; padding-right: 4px; }
    body.copies-4 .header { padding-bottom: 3px; margin-bottom: 4px; }
    body.copies-4 .receipt-title { font-size: 9px; margin: 2px 0; }
    body.copies-4 .info-table { margin-bottom: 3px; }
    body.copies-4 .info-table td { padding: 1px 2px; font-size: 7.5px; }
    body.copies-4 .label { width: 30%; }
    body.copies-4 .details-table { margin-bottom: 3px; }
    body.copies-4 .details-table th, body.copies-4 .details-table td { padding: 2px 3px; font-size: 7.5px; }
    body.copies-4 .footer { margin-top: 4px; }
    body.copies-4 .qr-code { width: 38px; height: 38px; }
    body.copies-4 .qr-text { font-size: 6px; }
    body.copies-4 .signature-line { width: 80px; padding-top: 2px; font-size: 7px; }
    body.copies-4 .copy-label { font-size: 7px; padding: 1px 4px; }

    /* 4 copies grid layout: each .copy-page is half the width, sits side-by-side */
    body.copies-4 .copies-grid { width: 100%; border-collapse: collapse; }
    body.copies-4 .copies-grid td { width: 50%; vertical-align: top; padding: 0; }
    body.copies-4 .copies-grid td.left  { padding-right: 4px; border-right: 1px dashed #999; }
    body.copies-4 .copies-grid td.right { padding-left: 4px; }
    body.copies-4 .copies-grid tr.bottom-row td { border-top: 1px dashed #999; padding-top: 4px; }
</style>
