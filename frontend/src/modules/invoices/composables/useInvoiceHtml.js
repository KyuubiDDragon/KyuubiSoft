import api from '@/core/api/axios'

export function useInvoiceHtml() {
  function escapeHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')
  }

  function formatCurrency(amount) {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount || 0)
  }

  function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleDateString('de-DE')
  }

  async function loadLogoDataUrl(logoFileId) {
    if (!logoFileId) return ''
    try {
      const logoResp = await api.get(`/api/v1/storage/${logoFileId}/thumbnail`, { responseType: 'blob' })
      return await new Promise((resolve) => {
        const reader = new FileReader()
        reader.onloadend = () => resolve(reader.result)
        reader.readAsDataURL(logoResp.data)
      })
    } catch {
      return ''
    }
  }

  function generateHtml(inv, senderSettings, logoDataUrl = '') {
    const docType = inv.document_type || 'invoice'
    const isKleinunternehmer = parseFloat(inv.tax_rate) === 0
    const isCreditNote = docType === 'credit_note'
    const isQuote = docType === 'quote'
    const isProforma = docType === 'proforma'
    const isReminder = docType === 'reminder'

    const mahnung_labels = ['Zahlungserinnerung', '1. Mahnung', '2. Mahnung', '3. Mahnung (Letzte Frist)']
    const mahnungLabel = mahnung_labels[inv.mahnung_level ?? 0] ?? 'Zahlungserinnerung'

    const docTitles = {
      invoice: 'Rechnung',
      proforma: 'Proforma-Rechnung',
      quote: 'Angebot',
      credit_note: 'Gutschrift',
      reminder: mahnungLabel,
    }
    const docTitle = docTitles[docType] ?? 'Rechnung'

    const steuernummer = senderSettings?.invoice_steuernummer || ''

    const signedAmount = (amount) =>
      isCreditNote ? formatCurrency(-(Math.abs(amount ?? 0))) : formatCurrency(amount ?? 0)

    const itemsHtml = (inv.items || []).map(item => `
      <tr>
        <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;vertical-align:top;">${escapeHtml(item.description ?? '').replace(/\n/g, '<br>')}</td>
        <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;">${parseFloat(item.quantity ?? 0).toLocaleString('de-DE')} ${escapeHtml(item.unit ?? '')}</td>
        <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;">${signedAmount(item.unit_price)}</td>
        <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;font-weight:500;">${signedAmount(item.total)}</td>
      </tr>`).join('')

    const totalLabel = isCreditNote ? 'Gutschriftbetrag' : 'Gesamtbetrag'
    let totalsHtml
    if (isProforma || isQuote) {
      totalsHtml = `<tr><td colspan="3" style="padding:10px 14px;text-align:right;font-weight:600;color:#374151;">${totalLabel} (netto)</td><td style="padding:10px 14px;text-align:right;font-weight:700;font-size:15px;">${signedAmount(inv.subtotal)}</td></tr>`
    } else if (isKleinunternehmer) {
      totalsHtml = `<tr><td colspan="3" style="padding:10px 14px;text-align:right;font-weight:600;color:#374151;">${totalLabel} (netto)</td><td style="padding:10px 14px;text-align:right;font-weight:700;font-size:15px;">${signedAmount(inv.subtotal)}</td></tr>`
    } else {
      totalsHtml = `
        <tr style="background:#f9fafb;">
          <td colspan="3" style="padding:8px 14px;text-align:right;color:#6b7280;">Nettobetrag</td>
          <td style="padding:8px 14px;text-align:right;color:#374151;">${signedAmount(inv.subtotal)}</td>
        </tr>
        <tr style="background:#f9fafb;">
          <td colspan="3" style="padding:8px 14px;text-align:right;color:#6b7280;">MwSt. ${escapeHtml(String(inv.tax_rate))}%</td>
          <td style="padding:8px 14px;text-align:right;color:#374151;">${signedAmount(inv.tax_amount)}</td>
        </tr>
        <tr>
          <td colspan="3" style="padding:12px 14px;text-align:right;font-weight:700;border-top:2px solid #111827;font-size:14px;">${totalLabel}</td>
          <td style="padding:12px 14px;text-align:right;font-weight:700;border-top:2px solid #111827;font-size:16px;color:#111827;">${signedAmount(inv.total)}</td>
        </tr>`
    }

    const senderTaxLine = (!isProforma && !isQuote)
      ? (isKleinunternehmer && steuernummer
          ? `<div style="color:#6b7280;font-size:11px;margin-top:3px;">Steuernummer: ${escapeHtml(steuernummer)}</div>`
          : (inv.sender_vat_id ? `<div style="color:#6b7280;font-size:11px;margin-top:3px;">USt-IdNr.: ${escapeHtml(inv.sender_vat_id)}</div>` : ''))
      : ''

    const dueDateLabel = isQuote ? 'Angebot gültig bis' : (isReminder ? 'Neue Zahlungsfrist bis' : 'Fällig bis')
    const issueDateLabel = isQuote ? 'Angebotsdatum' : (isProforma ? 'Ausstellungsdatum' : (isReminder ? 'Mahndatum' : 'Rechnungsdatum'))

    const proformaNotice = isProforma
      ? `<div style="margin-top:20px;padding:14px 16px;background:#eff6ff;border-left:4px solid #3b82f6;border-radius:4px;font-size:12px;color:#1e40af;">
          Dieses Dokument ist eine Proforma-Rechnung und kein steuerliches Dokument im Sinne des § 14 UStG. Es entsteht keine Zahlungsverpflichtung.
         </div>` : ''

    const mahnungNotice = isReminder
      ? `<div style="margin-top:20px;padding:14px 16px;background:#fff7ed;border-left:4px solid #f97316;border-radius:4px;font-size:12px;color:#9a3412;">
          <strong style="display:block;margin-bottom:6px;">${mahnungLabel}</strong>
          Wir erlauben uns, Sie an die Begleichung der ausstehenden Rechnung zu erinnern.
          ${inv.mahnung_fee > 0 ? `<br>Mahngebühr: ${formatCurrency(inv.mahnung_fee)}` : ''}
         </div>` : ''

    const paymentTerms = inv.payment_terms || senderSettings?.default_payment_terms || ''
    const showPaymentBox = !isProforma && !isQuote && (paymentTerms || inv.sender_bank_details)

    const kleinunternehmerNotice = (isKleinunternehmer && !isProforma && !isQuote)
      ? `<div style="margin-top:16px;font-size:11px;color:#6b7280;line-height:1.5;">
          Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.
         </div>` : ''

    return `<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">
    <style>
      * { box-sizing: border-box; }
      body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #111827; margin: 0; padding: 48px; line-height: 1.5; background: #fff; }
      h1 { font-size: 26px; font-weight: 800; margin: 0 0 4px; letter-spacing: -0.5px; }
      .label { color: #9ca3af; font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 3px; font-weight: 600; }
      table { width: 100%; border-collapse: collapse; margin-top: 28px; }
      thead th { background: #f3f4f6; padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 600; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em; }
      thead th:not(:first-child) { text-align: right; }
      .divider { border: none; border-top: 1px solid #e5e7eb; margin: 28px 0; }
      @media print { body { padding: 20mm; } }
    </style>
    </head><body>

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:40px;">
      <div>
        ${logoDataUrl ? `<img src="${logoDataUrl}" alt="Logo" style="max-height:64px;max-width:200px;object-fit:contain;margin-bottom:12px;display:block;" />` : ''}
        <h1>${docTitle}</h1>
        <div style="color:#6b7280;font-size:13px;margin-top:2px;">${escapeHtml(inv.invoice_number)}</div>
      </div>
      <div style="text-align:right;min-width:160px;">
        <div class="label">${issueDateLabel}</div>
        <div style="font-weight:600;">${formatDate(inv.issue_date)}</div>
        ${!isProforma && inv.service_date ? `<div class="label" style="margin-top:10px;">Leistungsdatum</div><div style="font-weight:600;">${formatDate(inv.service_date)}</div>` : ''}
        ${inv.due_date ? `<div class="label" style="margin-top:10px;">${escapeHtml(dueDateLabel)}</div><div style="font-weight:600;">${formatDate(inv.due_date)}</div>` : ''}
      </div>
    </div>

    <!-- Sender / Recipient -->
    <div style="display:flex;gap:60px;margin-bottom:36px;">
      <div style="flex:1;min-width:0;">
        <div class="label">Von</div>
        <div style="font-weight:700;font-size:14px;">${escapeHtml(inv.sender_name ?? '')}</div>
        ${inv.sender_company ? `<div style="color:#374151;">${escapeHtml(inv.sender_company)}</div>` : ''}
        ${inv.sender_address ? `<div style="white-space:pre-line;color:#4b5563;margin-top:4px;">${escapeHtml(inv.sender_address)}</div>` : ''}
        ${senderTaxLine}
        ${inv.sender_email ? `<div style="color:#6b7280;font-size:12px;margin-top:4px;">${escapeHtml(inv.sender_email)}</div>` : ''}
        ${inv.sender_phone ? `<div style="color:#6b7280;font-size:12px;">${escapeHtml(inv.sender_phone)}</div>` : ''}
      </div>
      <div style="flex:1;min-width:0;">
        <div class="label">${isQuote ? 'Angeboten für' : (isCreditNote ? 'Gutschrift für' : (isReminder ? 'Empfänger' : 'Rechnungsempfänger'))}</div>
        <div style="font-weight:700;font-size:14px;">${escapeHtml(inv.client_name ?? '')}</div>
        ${inv.client_company ? `<div style="color:#374151;">${escapeHtml(inv.client_company)}</div>` : ''}
        ${inv.client_address ? `<div style="white-space:pre-line;color:#4b5563;margin-top:4px;">${escapeHtml(inv.client_address)}</div>` : ''}
        ${inv.client_email ? `<div style="color:#6b7280;font-size:12px;margin-top:4px;">${escapeHtml(inv.client_email)}</div>` : ''}
        ${inv.client_vat_id ? `<div style="color:#9ca3af;font-size:11px;">USt-IdNr.: ${escapeHtml(inv.client_vat_id)}</div>` : ''}
      </div>
    </div>

    <!-- Line items -->
    <table>
      <thead><tr>
        <th style="width:50%;">Beschreibung</th>
        <th style="text-align:right;width:15%;">Menge</th>
        <th style="text-align:right;width:17%;">Einzelpreis</th>
        <th style="text-align:right;width:18%;">Betrag</th>
      </tr></thead>
      <tbody>${itemsHtml || '<tr><td colspan="4" style="padding:16px;text-align:center;color:#9ca3af;">Keine Positionen</td></tr>'}</tbody>
      <tfoot>${totalsHtml}</tfoot>
    </table>

    ${inv.notes ? `<div style="margin-top:28px;"><div class="label">Anmerkungen</div><div style="color:#4b5563;margin-top:4px;">${escapeHtml(inv.notes).replace(/\n/g, '<br>')}</div></div>` : ''}
    ${(!isProforma && inv.terms) ? `<div style="margin-top:20px;padding:14px 16px;background:#f9fafb;border-left:4px solid #d1d5db;border-radius:4px;font-size:12px;color:#4b5563;">${escapeHtml(inv.terms).replace(/\n/g, '<br>')}</div>` : ''}
    ${proformaNotice}
    ${mahnungNotice}
    ${kleinunternehmerNotice}

    ${showPaymentBox ? `
    <div style="margin-top:28px;padding:16px 20px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;">
      <div class="label" style="margin-bottom:8px;">Zahlung</div>
      ${paymentTerms ? `<div style="font-weight:600;color:#111827;margin-bottom:6px;">${escapeHtml(paymentTerms)}</div>` : ''}
      ${inv.sender_bank_details ? `<div style="white-space:pre-line;color:#6b7280;font-size:12px;">${escapeHtml(inv.sender_bank_details)}</div>` : ''}
    </div>` : ''}

    </body></html>`
  }

  async function downloadPdf(inv, senderSettings) {
    const logoDataUrl = await loadLogoDataUrl(inv.sender_logo_file_id || senderSettings?.logo_file_id)
    const html = generateHtml(inv, senderSettings, logoDataUrl)
    const { default: html2pdf } = await import('html2pdf.js')
    await html2pdf().set({
      margin: 0,
      filename: `${inv.invoice_number}.pdf`,
      html2canvas: { scale: 2, useCORS: true },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    }).from(html, 'string').save()
  }

  return {
    generateHtml,
    downloadPdf,
    loadLogoDataUrl,
  }
}
