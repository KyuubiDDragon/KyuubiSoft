import api from '@/core/api/axios'

/**
 * Invoice line item for HTML generation
 */
export interface InvoiceHtmlItem {
  description?: string
  quantity?: number
  unit?: string
  unit_price?: number
  total?: number
}

/**
 * Invoice data used for HTML generation
 */
export interface InvoiceHtmlData {
  invoice_number?: string
  document_type?: string
  language?: string
  issue_date?: string
  due_date?: string
  service_date?: string
  sender_name?: string
  sender_company?: string
  sender_address?: string
  sender_email?: string
  sender_phone?: string
  sender_vat_id?: string
  sender_logo_file_id?: string | null
  sender_bank_details?: string
  client_name?: string
  client_company?: string
  client_address?: string
  client_email?: string
  client_vat_id?: string
  items?: InvoiceHtmlItem[]
  subtotal?: number
  tax_rate?: string | number
  tax_amount?: number
  total?: number
  notes?: string
  terms?: string
  payment_terms?: string
  mahnung_level?: number
  mahnung_fee?: number
  [key: string]: unknown
}

/**
 * Sender settings used for HTML generation
 */
export interface InvoiceSenderHtmlSettings {
  invoice_steuernummer?: string
  logo_file_id?: string | null
  default_payment_terms?: string
  [key: string]: unknown
}

/**
 * Return type of the useInvoiceHtml composable
 */
export interface UseInvoiceHtmlReturn {
  generateHtml: (inv: InvoiceHtmlData, senderSettings: InvoiceSenderHtmlSettings, logoDataUrl?: string) => string
  downloadPdf: (inv: InvoiceHtmlData, senderSettings: InvoiceSenderHtmlSettings, editedHtml?: string) => Promise<void>
  loadLogoDataUrl: (logoFileId: string | null | undefined) => Promise<string>
}

// ─── Translations ───────────────────────────────────────────────────────────

interface InvoiceTranslations {
  docTitles: Record<string, string>
  mahnungLabels: string[]
  tableHeaders: { description: string; quantity: string; unitPrice: string; amount: string }
  from: string
  recipientLabels: Record<string, string>
  issueDateLabels: Record<string, string>
  dueDateLabels: Record<string, string>
  serviceDate: string
  vatId: string
  taxNumber: string
  netAmount: string
  vat: string
  totalLabel: string
  creditNoteTotal: string
  notes: string
  payment: string
  noItems: string
  kleinunternehmerNotice: string
  reverseChargeNotice: string
  licenseNotice: string
  proformaNotice: string
  reminderNotice: string
  reminderFee: string
  units: Record<string, string>
}

const translations: Record<string, InvoiceTranslations> = {
  de: {
    docTitles: { invoice: 'Rechnung', proforma: 'Proforma-Rechnung', quote: 'Angebot', credit_note: 'Gutschrift', reminder: '' },
    mahnungLabels: ['Zahlungserinnerung', '1. Mahnung', '2. Mahnung', '3. Mahnung (Letzte Frist)'],
    tableHeaders: { description: 'Beschreibung', quantity: 'Menge', unitPrice: 'Einzelpreis', amount: 'Betrag' },
    from: 'Von',
    recipientLabels: { invoice: 'Rechnungsempf\u00E4nger', quote: 'Angeboten f\u00FCr', credit_note: 'Gutschrift f\u00FCr', reminder: 'Empf\u00E4nger', proforma: 'Empf\u00E4nger' },
    issueDateLabels: { invoice: 'Rechnungsdatum', quote: 'Angebotsdatum', proforma: 'Ausstellungsdatum', reminder: 'Mahndatum', credit_note: 'Gutschriftdatum' },
    dueDateLabels: { invoice: 'F\u00E4llig bis', quote: 'Angebot g\u00FCltig bis', reminder: 'Neue Zahlungsfrist bis', proforma: 'F\u00E4llig bis', credit_note: 'F\u00E4llig bis' },
    serviceDate: 'Leistungsdatum',
    vatId: 'USt-IdNr.',
    taxNumber: 'Steuernummer',
    netAmount: 'Nettobetrag',
    vat: 'MwSt.',
    totalLabel: 'Gesamtbetrag',
    creditNoteTotal: 'Gutschriftbetrag',
    notes: 'Anmerkungen',
    payment: 'Zahlung',
    noItems: 'Keine Positionen',
    kleinunternehmerNotice: 'Gem\u00E4\u00DF \u00A7 19 UStG wird keine Umsatzsteuer berechnet.',
    reverseChargeNotice: 'Reverse Charge \u2013 Die Steuerschuldnerschaft geht auf den Leistungsempf\u00E4nger \u00FCber gem\u00E4\u00DF Art. 44 und Art. 196 der EU-Mehrwertsteuerrichtlinie.',
    licenseNotice: 'Non-exclusive, non-transferable Lizenz. Die Nutzung ist beschr\u00E4nkt auf den Server des Kunden. Weitervertrieb oder Weiterverkauf ist nicht gestattet.',
    proformaNotice: 'Dieses Dokument ist eine Proforma-Rechnung und kein steuerliches Dokument im Sinne des \u00A7 14 UStG. Es entsteht keine Zahlungsverpflichtung.',
    reminderNotice: 'Wir erlauben uns, Sie an die Begleichung der ausstehenden Rechnung zu erinnern.',
    reminderFee: 'Mahngeb\u00FChr',
    units: { 'Stunde': 'Stunde', 'Stück': 'Stück', 'Pauschal': 'Pauschal', 'Tag': 'Tag', 'Monat': 'Monat', 'km': 'km' },
  },
  en: {
    docTitles: { invoice: 'Invoice', proforma: 'Proforma Invoice', quote: 'Quote', credit_note: 'Credit Note', reminder: '' },
    mahnungLabels: ['Payment Reminder', '1st Reminder', '2nd Reminder', '3rd Reminder (Final Notice)'],
    tableHeaders: { description: 'Description', quantity: 'Quantity', unitPrice: 'Unit Price', amount: 'Amount' },
    from: 'From',
    recipientLabels: { invoice: 'Bill To', quote: 'Quoted To', credit_note: 'Credit To', reminder: 'Recipient', proforma: 'Recipient' },
    issueDateLabels: { invoice: 'Invoice Date', quote: 'Quote Date', proforma: 'Issue Date', reminder: 'Reminder Date', credit_note: 'Credit Note Date' },
    dueDateLabels: { invoice: 'Due Date', quote: 'Valid Until', reminder: 'New Payment Deadline', proforma: 'Due Date', credit_note: 'Due Date' },
    serviceDate: 'Service Date',
    vatId: 'VAT ID',
    taxNumber: 'Tax Number',
    netAmount: 'Net Amount',
    vat: 'VAT',
    totalLabel: 'Total',
    creditNoteTotal: 'Credit Note Total',
    notes: 'Notes',
    payment: 'Payment',
    noItems: 'No items',
    kleinunternehmerNotice: 'No VAT charged pursuant to \u00A7 19 UStG (German small business regulation).',
    reverseChargeNotice: 'Reverse charge \u2013 VAT to be accounted for by the recipient in accordance with Art. 44 and Art. 196 of the EU VAT Directive.',
    licenseNotice: 'Non-exclusive, non-transferable license. License is limited to use on the customer\u2019s server. Redistribution or resale is not permitted.',
    proformaNotice: 'This document is a proforma invoice and does not constitute a tax document under \u00A7 14 UStG. No payment obligation arises.',
    reminderNotice: 'We kindly remind you of the outstanding invoice payment.',
    reminderFee: 'Reminder fee',
    units: { 'Stunde': 'hour', 'Stück': 'piece', 'Pauschal': 'flat rate', 'Tag': 'day', 'Monat': 'month', 'km': 'km' },
  },
}

// ─── Composable ─────────────────────────────────────────────────────────────

export function useInvoiceHtml(): UseInvoiceHtmlReturn {
  function escapeHtml(str: string | undefined | null): string {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')
  }

  function formatCurrency(amount: number | undefined, lang: string): string {
    const locale = lang === 'en' ? 'en-GB' : 'de-DE'
    return new Intl.NumberFormat(locale, { style: 'currency', currency: 'EUR' }).format(amount || 0)
  }

  function formatDate(dateStr: string | undefined, lang: string): string {
    if (!dateStr) return '-'
    const locale = lang === 'en' ? 'en-GB' : 'de-DE'
    return new Date(dateStr).toLocaleDateString(locale)
  }

  async function loadLogoDataUrl(logoFileId: string | null | undefined): Promise<string> {
    if (!logoFileId) return ''
    try {
      const logoResp = await api.get(`/api/v1/storage/${logoFileId}/thumbnail`, { responseType: 'blob' })
      return await new Promise<string>((resolve) => {
        const reader = new FileReader()
        reader.onloadend = () => resolve(reader.result as string)
        reader.readAsDataURL(logoResp.data)
      })
    } catch {
      return ''
    }
  }

  function generateHtml(inv: InvoiceHtmlData, senderSettings: InvoiceSenderHtmlSettings, logoDataUrl: string = ''): string {
    const lang = inv.language || 'de'
    const t = translations[lang] || translations.de
    const docType = inv.document_type || 'invoice'
    const isKleinunternehmer = parseFloat(String(inv.tax_rate)) === 0
    const isCreditNote = docType === 'credit_note'
    const isQuote = docType === 'quote'
    const isProforma = docType === 'proforma'
    const isReminder = docType === 'reminder'

    // Detect Reverse Charge: client has EU VAT-ID (not DE)
    const clientVatId = (inv.client_vat_id || '').trim().toUpperCase()
    const euCountries = ['AT','BE','BG','CY','CZ','DK','EE','EL','ES','FI','FR','HR','HU','IE','IT','LT','LU','LV','MT','NL','PL','PT','RO','SE','SI','SK']
    const isReverseCharge = clientVatId.length >= 2 && euCountries.includes(clientVatId.substring(0, 2))

    const mahnungLabel = t.mahnungLabels[inv.mahnung_level ?? 0] ?? t.mahnungLabels[0]
    const docTitle = isReminder ? mahnungLabel : (t.docTitles[docType] ?? t.docTitles.invoice)

    const steuernummer = senderSettings?.invoice_steuernummer || ''

    const signedAmount = (amount: number | undefined): string =>
      isCreditNote ? formatCurrency(-(Math.abs(amount ?? 0)), lang) : formatCurrency(amount ?? 0, lang)

    const translateUnit = (unit: string | undefined): string => {
      if (!unit) return ''
      return t.units[unit] ?? unit
    }

    const itemsHtml = (inv.items || []).map((item: InvoiceHtmlItem) => `
      <tr>
        <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;vertical-align:top;">${escapeHtml(item.description ?? '').replace(/\n/g, '<br>')}</td>
        <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;">${parseFloat(String(item.quantity ?? 0)).toLocaleString(lang === 'en' ? 'en-GB' : 'de-DE')} ${escapeHtml(translateUnit(item.unit))}</td>
        <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;">${signedAmount(item.unit_price)}</td>
        <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;font-weight:500;">${signedAmount(item.total)}</td>
      </tr>`).join('')

    const totalLabel = isCreditNote ? t.creditNoteTotal : t.totalLabel
    let totalsHtml: string
    if (isProforma || isQuote || isKleinunternehmer) {
      totalsHtml = `<tr><td colspan="3" style="padding:10px 14px;text-align:right;font-weight:600;color:#374151;">${totalLabel} (${lang === 'en' ? 'net' : 'netto'})</td><td style="padding:10px 14px;text-align:right;font-weight:700;font-size:15px;">${signedAmount(inv.subtotal)}</td></tr>`
    } else {
      totalsHtml = `
        <tr style="background:#f9fafb;">
          <td colspan="3" style="padding:8px 14px;text-align:right;color:#6b7280;">${t.netAmount}</td>
          <td style="padding:8px 14px;text-align:right;color:#374151;">${signedAmount(inv.subtotal)}</td>
        </tr>
        <tr style="background:#f9fafb;">
          <td colspan="3" style="padding:8px 14px;text-align:right;color:#6b7280;">${t.vat} ${escapeHtml(String(inv.tax_rate))}%</td>
          <td style="padding:8px 14px;text-align:right;color:#374151;">${signedAmount(inv.tax_amount)}</td>
        </tr>
        <tr>
          <td colspan="3" style="padding:12px 14px;text-align:right;font-weight:700;border-top:2px solid #111827;font-size:14px;">${totalLabel}</td>
          <td style="padding:12px 14px;text-align:right;font-weight:700;border-top:2px solid #111827;font-size:16px;color:#111827;">${signedAmount(inv.total)}</td>
        </tr>`
    }

    const senderTaxLine = (!isProforma && !isQuote)
      ? (isKleinunternehmer && steuernummer
          ? `<div style="color:#6b7280;font-size:11px;margin-top:3px;">${t.taxNumber}: ${escapeHtml(steuernummer)}</div>`
          : (inv.sender_vat_id ? `<div style="color:#6b7280;font-size:11px;margin-top:3px;">${t.vatId}: ${escapeHtml(inv.sender_vat_id)}</div>` : ''))
      : ''

    const dueDateLabel = t.dueDateLabels[docType] ?? t.dueDateLabels.invoice
    const issueDateLabel = t.issueDateLabels[docType] ?? t.issueDateLabels.invoice
    const recipientLabel = t.recipientLabels[docType] ?? t.recipientLabels.invoice

    const proformaNotice = isProforma
      ? `<div style="margin-top:20px;padding:14px 16px;background:#eff6ff;border-left:4px solid #3b82f6;border-radius:4px;font-size:12px;color:#1e40af;">
          ${t.proformaNotice}
         </div>` : ''

    const mahnungNotice = isReminder
      ? `<div style="margin-top:20px;padding:14px 16px;background:#fff7ed;border-left:4px solid #f97316;border-radius:4px;font-size:12px;color:#9a3412;">
          <strong style="display:block;margin-bottom:6px;">${mahnungLabel}</strong>
          ${t.reminderNotice}
          ${(inv.mahnung_fee ?? 0) > 0 ? `<br>${t.reminderFee}: ${formatCurrency(inv.mahnung_fee, lang)}` : ''}
         </div>` : ''

    const paymentTerms = inv.payment_terms || senderSettings?.default_payment_terms || ''
    const showPaymentBox = !isProforma && !isQuote && (paymentTerms || inv.sender_bank_details)

    const kleinunternehmerNotice = (isKleinunternehmer && !isProforma && !isQuote)
      ? `<div style="margin-top:16px;font-size:11px;color:#6b7280;line-height:1.5;">
          ${t.kleinunternehmerNotice}
         </div>` : ''

    // Reverse-Charge: client has a non-DE VAT ID → intra-EU B2B
    const clientVat = (inv.client_vat_id ?? '').trim().toUpperCase()
    const isIntraEu = clientVat.length > 0 && !clientVat.startsWith('DE')
    const reverseChargeNotice = (isIntraEu && !isProforma && !isQuote)
      ? `<div style="margin-top:12px;font-size:11px;color:#6b7280;line-height:1.5;">
          ${t.reverseChargeNotice}
         </div>` : ''

    // License limitation notice
    const licenseNotice = (!isProforma && !isQuote && !isReminder && !isCreditNote)
      ? `<div style="margin-top:8px;font-size:11px;color:#6b7280;line-height:1.5;">
          ${t.licenseNotice}
         </div>` : ''

    return `<!DOCTYPE html><html lang="${lang}"><head><meta charset="UTF-8">
    <style>
      * { box-sizing: border-box; }
      body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #111827; margin: 0; padding: 48px; line-height: 1.5; background: #fff; }
      h1 { font-size: 26px; font-weight: 800; margin: 0 0 4px; letter-spacing: -0.5px; }
      .label { color: #9ca3af; font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 3px; font-weight: 600; }
      table { width: 100%; border-collapse: collapse; }
      .items-table { margin-top: 28px; }
      .items-table thead th { background: #f3f4f6; padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 600; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em; }
      .items-table thead th:not(:first-child) { text-align: right; }
      .divider { border: none; border-top: 1px solid #e5e7eb; margin: 28px 0; }
      @media print { body { padding: 20mm; } }
    </style>
    </head><body>

    <!-- Header -->
    <table style="width:100%;margin-bottom:40px;" cellpadding="0" cellspacing="0">
      <tr>
        <td style="vertical-align:top;">
          ${logoDataUrl ? `<img src="${logoDataUrl}" alt="Logo" style="max-height:64px;max-width:200px;object-fit:contain;margin-bottom:12px;display:block;" />` : ''}
          <h1>${docTitle}</h1>
          <div style="color:#6b7280;font-size:13px;margin-top:2px;">${escapeHtml(inv.invoice_number)}</div>
        </td>
        <td style="text-align:right;vertical-align:top;width:160px;">
          <div class="label">${issueDateLabel}</div>
          <div style="font-weight:600;">${formatDate(inv.issue_date, lang)}</div>
          ${!isProforma && inv.service_date ? `<div class="label" style="margin-top:10px;">${t.serviceDate}</div><div style="font-weight:600;">${formatDate(inv.service_date, lang)}</div>` : ''}
          ${inv.due_date ? `<div class="label" style="margin-top:10px;">${escapeHtml(dueDateLabel)}</div><div style="font-weight:600;">${formatDate(inv.due_date, lang)}</div>` : ''}
        </td>
      </tr>
    </table>

    <!-- Sender / Recipient -->
    <table style="width:100%;margin-bottom:36px;" cellpadding="0" cellspacing="0">
      <tr>
        <td style="width:45%;vertical-align:top;">
          <div class="label">${t.from}</div>
          <div style="font-weight:700;font-size:14px;">${escapeHtml(inv.sender_name ?? '')}</div>
          ${inv.sender_company ? `<div style="color:#374151;">${escapeHtml(inv.sender_company)}</div>` : ''}
          ${inv.sender_address ? `<div style="white-space:pre-line;color:#4b5563;margin-top:4px;">${escapeHtml(inv.sender_address)}</div>` : ''}
          ${senderTaxLine}
          ${inv.sender_email ? `<div style="color:#6b7280;font-size:12px;margin-top:4px;">${escapeHtml(inv.sender_email)}</div>` : ''}
          ${inv.sender_phone ? `<div style="color:#6b7280;font-size:12px;">${escapeHtml(inv.sender_phone)}</div>` : ''}
        </td>
        <td style="width:10%;"></td>
        <td style="width:45%;vertical-align:top;">
          <div class="label">${recipientLabel}</div>
          <div style="font-weight:700;font-size:14px;">${escapeHtml(inv.client_name ?? '')}</div>
          ${inv.client_company ? `<div style="color:#374151;">${escapeHtml(inv.client_company)}</div>` : ''}
          ${inv.client_address ? `<div style="white-space:pre-line;color:#4b5563;margin-top:4px;">${escapeHtml(inv.client_address)}</div>` : ''}
          ${inv.client_email ? `<div style="color:#6b7280;font-size:12px;margin-top:4px;">${escapeHtml(inv.client_email)}</div>` : ''}
          ${inv.client_vat_id ? `<div style="color:#9ca3af;font-size:11px;">${t.vatId}: ${escapeHtml(inv.client_vat_id)}</div>` : ''}
        </td>
      </tr>
    </table>

    <!-- Line items -->
    <table class="items-table">
      <thead><tr>
        <th style="width:50%;">${t.tableHeaders.description}</th>
        <th style="text-align:right;width:15%;">${t.tableHeaders.quantity}</th>
        <th style="text-align:right;width:17%;">${t.tableHeaders.unitPrice}</th>
        <th style="text-align:right;width:18%;">${t.tableHeaders.amount}</th>
      </tr></thead>
      <tbody>${itemsHtml || `<tr><td colspan="4" style="padding:16px;text-align:center;color:#9ca3af;">${t.noItems}</td></tr>`}</tbody>
      <tfoot>${totalsHtml}</tfoot>
    </table>

    ${inv.notes ? `<div style="margin-top:28px;"><div class="label">${t.notes}</div><div style="color:#4b5563;margin-top:4px;">${escapeHtml(inv.notes).replace(/\n/g, '<br>')}</div></div>` : ''}
    ${(!isProforma && inv.terms) ? `<div style="margin-top:20px;padding:14px 16px;background:#f9fafb;border-left:4px solid #d1d5db;border-radius:4px;font-size:12px;color:#4b5563;">${escapeHtml(inv.terms).replace(/\n/g, '<br>')}</div>` : ''}
    ${proformaNotice}
    ${mahnungNotice}
    ${kleinunternehmerNotice}
    ${reverseChargeNotice}
    ${licenseNotice}

    ${showPaymentBox ? `
    <div style="margin-top:28px;padding:16px 20px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;">
      <div class="label" style="margin-bottom:8px;">${t.payment}</div>
      ${paymentTerms ? `<div style="font-weight:600;color:#111827;margin-bottom:6px;">${escapeHtml(paymentTerms)}</div>` : ''}
      ${inv.sender_bank_details ? `<div style="white-space:pre-line;color:#6b7280;font-size:12px;">${escapeHtml(inv.sender_bank_details)}</div>` : ''}
    </div>` : ''}

    </body></html>`
  }

  async function downloadPdf(inv: InvoiceHtmlData & { id?: string }, senderSettings: InvoiceSenderHtmlSettings, editedHtml?: string): Promise<void> {
    let html = editedHtml || ''
    if (!html) {
      const logoDataUrl = await loadLogoDataUrl(inv.sender_logo_file_id || senderSettings?.logo_file_id)
      html = generateHtml(inv, senderSettings, logoDataUrl)
    }

    const res = await api.post(`/api/v1/invoices/${inv.id}/pdf`, { html }, {
      responseType: 'blob',
    })

    const blob = new Blob([res.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${inv.invoice_number}.pdf`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  }

  return {
    generateHtml,
    downloadPdf,
    loadLogoDataUrl,
  }
}
