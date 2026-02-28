import api from '@/core/api/axios'
import type { Contract } from './useContracts'

export interface UseContractHtmlReturn {
  generateHtml: (contract: Contract, logoDataUrl?: string) => string
  downloadPdf: (contract: Contract) => Promise<void>
  loadLogoDataUrl: (logoFileId: string | null | undefined) => Promise<string>
}

export function useContractHtml(): UseContractHtmlReturn {
  function escapeHtml(str: string | undefined | null): string {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')
  }

  function formatCurrency(amount: number | undefined, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format(amount || 0)
  }

  function formatDate(dateStr: string | undefined): string {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleDateString('de-DE')
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

  const contractTypeLabels: Record<string, Record<string, string>> = {
    de: {
      license: 'Softwarelizenzvertrag',
      development: 'Softwareentwicklungsvertrag',
      saas: 'Software-as-a-Service-Vertrag',
      maintenance: 'Wartungs- und Supportvertrag',
      nda: 'Geheimhaltungsvereinbarung',
    },
    en: {
      license: 'Software License Agreement',
      development: 'Software Development Agreement',
      saas: 'Software as a Service Agreement',
      maintenance: 'Maintenance and Support Agreement',
      nda: 'Non-Disclosure Agreement',
    },
  }

  function generateHtml(contract: Contract, logoDataUrl: string = ''): string {
    const lang = contract.language || 'de'
    const isDe = lang === 'de'
    const typeLabel = contractTypeLabels[lang]?.[contract.contract_type] || contract.contract_type

    // Labels based on language
    const l = isDe ? {
      contractNumber: 'Vertragsnummer',
      date: 'Datum',
      partyA: 'Auftragnehmer',
      partyB: 'Auftraggeber',
      startDate: 'Vertragsbeginn',
      endDate: 'Vertragsende',
      autoRenewal: 'Automatische Verlaengerung',
      noticePeriod: 'Kuendigungsfrist',
      days: 'Tage',
      totalValue: 'Vertragswert',
      paymentSchedule: 'Zahlungsplan',
      governingLaw: 'Anwendbares Recht',
      jurisdiction: 'Gerichtsstand',
      yes: 'Ja',
      no: 'Nein',
      notes: 'Anmerkungen',
      signaturePartyA: 'Unterschrift Auftragnehmer',
      signaturePartyB: 'Unterschrift Auftraggeber',
      datePlace: 'Ort, Datum',
      vatId: 'USt-IdNr.',
      email: 'E-Mail',
      paymentLabels: { 'one-time': 'Einmalig', monthly: 'Monatlich', quarterly: 'Quartalsweise', yearly: 'Jaehrlich' } as Record<string, string>,
      lawLabels: { DE: 'Deutsches Recht', AT: 'Oesterreichisches Recht', CH: 'Schweizer Recht', DK: 'Daenisches Recht' } as Record<string, string>,
      renewal: { monthly: 'Monatlich', yearly: 'Jaehrlich' } as Record<string, string>,
    } : {
      contractNumber: 'Contract Number',
      date: 'Date',
      partyA: 'Service Provider',
      partyB: 'Client',
      startDate: 'Start Date',
      endDate: 'End Date',
      autoRenewal: 'Auto Renewal',
      noticePeriod: 'Notice Period',
      days: 'days',
      totalValue: 'Contract Value',
      paymentSchedule: 'Payment Schedule',
      governingLaw: 'Governing Law',
      jurisdiction: 'Jurisdiction',
      yes: 'Yes',
      no: 'No',
      notes: 'Notes',
      signaturePartyA: 'Service Provider Signature',
      signaturePartyB: 'Client Signature',
      datePlace: 'Place, Date',
      vatId: 'VAT ID',
      email: 'Email',
      paymentLabels: { 'one-time': 'One-time', monthly: 'Monthly', quarterly: 'Quarterly', yearly: 'Yearly' } as Record<string, string>,
      lawLabels: { DE: 'German Law', AT: 'Austrian Law', CH: 'Swiss Law', DK: 'Danish Law', US: 'US Law', GB: 'UK Law' } as Record<string, string>,
      renewal: { monthly: 'Monthly', yearly: 'Yearly' } as Record<string, string>,
    }

    const paymentLabel = l.paymentLabels[contract.payment_schedule || ''] || contract.payment_schedule || '-'
    const lawLabel = l.lawLabels[contract.governing_law || 'DE'] || contract.governing_law || '-'
    const renewalLabel = l.renewal[contract.renewal_period || ''] || contract.renewal_period || '-'

    // Signature section
    const signatureA = contract.party_a_signature_data
      ? `<img src="${contract.party_a_signature_data}" alt="Signature" style="max-height:60px;max-width:200px;" /><div style="font-size:11px;color:#6b7280;margin-top:4px;">${formatDate(contract.party_a_signed_at ?? undefined)}</div>`
      : `<div style="height:60px;border-bottom:1px solid #9ca3af;width:200px;margin-top:10px;"></div>`
    const signatureB = contract.party_b_signature_data
      ? `<img src="${contract.party_b_signature_data}" alt="Signature" style="max-height:60px;max-width:200px;" /><div style="font-size:11px;color:#6b7280;margin-top:4px;">${formatDate(contract.party_b_signed_at ?? undefined)}</div>`
      : `<div style="height:60px;border-bottom:1px solid #9ca3af;width:200px;margin-top:10px;"></div>`

    return `<!DOCTYPE html><html lang="${lang}"><head><meta charset="UTF-8">
    <style>
      * { box-sizing: border-box; }
      body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #111827; margin: 0; padding: 48px; line-height: 1.6; background: #fff; }
      h1 { font-size: 24px; font-weight: 800; margin: 0 0 4px; letter-spacing: -0.5px; }
      h2 { font-size: 15px; font-weight: 700; margin: 24px 0 8px; padding-bottom: 6px; border-bottom: 2px solid #111827; }
      .label { color: #9ca3af; font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 3px; font-weight: 600; }
      .clause { margin-bottom: 16px; line-height: 1.7; }
      .clause p { margin: 0 0 8px; }
      .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 32px; margin: 12px 0; }
      .info-item .label { margin-bottom: 2px; }
      .info-item .value { font-weight: 600; color: #111827; }
      .divider { border: none; border-top: 1px solid #e5e7eb; margin: 28px 0; }
      @media print { body { padding: 20mm; } }
    </style>
    </head><body>

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:36px;">
      <div>
        ${logoDataUrl ? `<img src="${logoDataUrl}" alt="Logo" style="max-height:64px;max-width:200px;object-fit:contain;margin-bottom:12px;display:block;" />` : ''}
        <h1>${escapeHtml(typeLabel)}</h1>
        <div style="color:#6b7280;font-size:13px;margin-top:2px;">${l.contractNumber}: ${escapeHtml(contract.contract_number)}</div>
        ${contract.title ? `<div style="font-size:14px;font-weight:600;margin-top:4px;">${escapeHtml(contract.title)}</div>` : ''}
      </div>
      <div style="text-align:right;min-width:160px;">
        <div class="label">${l.date}</div>
        <div style="font-weight:600;">${formatDate(contract.created_at)}</div>
      </div>
    </div>

    <!-- Parties -->
    <div style="display:flex;gap:60px;margin-bottom:32px;">
      <div style="flex:1;min-width:0;">
        <div class="label">${l.partyA}</div>
        <div style="font-weight:700;font-size:14px;">${escapeHtml(contract.party_a_name ?? '')}</div>
        ${contract.party_a_company ? `<div style="color:#374151;">${escapeHtml(contract.party_a_company)}</div>` : ''}
        ${contract.party_a_address ? `<div style="white-space:pre-line;color:#4b5563;margin-top:4px;">${escapeHtml(contract.party_a_address)}</div>` : ''}
        ${contract.party_a_email ? `<div style="color:#6b7280;font-size:12px;margin-top:4px;">${l.email}: ${escapeHtml(contract.party_a_email)}</div>` : ''}
        ${contract.party_a_vat_id ? `<div style="color:#9ca3af;font-size:11px;">${l.vatId}: ${escapeHtml(contract.party_a_vat_id)}</div>` : ''}
      </div>
      <div style="flex:1;min-width:0;">
        <div class="label">${l.partyB}</div>
        <div style="font-weight:700;font-size:14px;">${escapeHtml(contract.party_b_name ?? '')}</div>
        ${contract.party_b_company ? `<div style="color:#374151;">${escapeHtml(contract.party_b_company)}</div>` : ''}
        ${contract.party_b_address ? `<div style="white-space:pre-line;color:#4b5563;margin-top:4px;">${escapeHtml(contract.party_b_address)}</div>` : ''}
        ${contract.party_b_email ? `<div style="color:#6b7280;font-size:12px;margin-top:4px;">${l.email}: ${escapeHtml(contract.party_b_email)}</div>` : ''}
        ${contract.party_b_vat_id ? `<div style="color:#9ca3af;font-size:11px;">${l.vatId}: ${escapeHtml(contract.party_b_vat_id)}</div>` : ''}
      </div>
    </div>

    <hr class="divider" />

    <!-- Contract Terms Summary -->
    <div class="info-grid">
      <div class="info-item"><div class="label">${l.startDate}</div><div class="value">${formatDate(contract.start_date)}</div></div>
      <div class="info-item"><div class="label">${l.endDate}</div><div class="value">${contract.end_date ? formatDate(contract.end_date) : (isDe ? 'Unbefristet' : 'Indefinite')}</div></div>
      <div class="info-item"><div class="label">${l.totalValue}</div><div class="value">${formatCurrency(contract.total_value, contract.currency || 'EUR')}</div></div>
      <div class="info-item"><div class="label">${l.paymentSchedule}</div><div class="value">${escapeHtml(paymentLabel)}</div></div>
      <div class="info-item"><div class="label">${l.autoRenewal}</div><div class="value">${contract.auto_renewal ? `${l.yes} (${renewalLabel})` : l.no}</div></div>
      <div class="info-item"><div class="label">${l.noticePeriod}</div><div class="value">${contract.notice_period_days || 30} ${l.days}</div></div>
      <div class="info-item"><div class="label">${l.governingLaw}</div><div class="value">${escapeHtml(lawLabel)}</div></div>
      ${contract.jurisdiction ? `<div class="info-item"><div class="label">${l.jurisdiction}</div><div class="value">${escapeHtml(contract.jurisdiction)}</div></div>` : ''}
    </div>

    <hr class="divider" />

    <!-- Contract Content (generated from template) -->
    <div class="contract-content">
      ${contract.content_html || ''}
    </div>

    ${contract.notes ? `<div style="margin-top:28px;"><div class="label">${l.notes}</div><div style="color:#4b5563;margin-top:4px;">${escapeHtml(contract.notes).replace(/\n/g, '<br>')}</div></div>` : ''}

    <!-- Signature Block -->
    <div style="margin-top:60px;display:flex;gap:60px;">
      <div style="flex:1;">
        <div class="label" style="margin-bottom:8px;">${l.signaturePartyA}</div>
        ${signatureA}
        <div style="margin-top:16px;color:#6b7280;font-size:11px;">${l.datePlace}</div>
        <div style="border-bottom:1px solid #d1d5db;width:200px;margin-top:20px;"></div>
        <div style="font-size:12px;margin-top:4px;">${escapeHtml(contract.party_a_name ?? '')}</div>
      </div>
      <div style="flex:1;">
        <div class="label" style="margin-bottom:8px;">${l.signaturePartyB}</div>
        ${signatureB}
        <div style="margin-top:16px;color:#6b7280;font-size:11px;">${l.datePlace}</div>
        <div style="border-bottom:1px solid #d1d5db;width:200px;margin-top:20px;"></div>
        <div style="font-size:12px;margin-top:4px;">${escapeHtml(contract.party_b_name ?? '')}</div>
      </div>
    </div>

    </body></html>`
  }

  async function downloadPdf(contract: Contract): Promise<void> {
    const logoDataUrl = await loadLogoDataUrl(null)
    const html = generateHtml(contract, logoDataUrl)
    const { default: html2pdf } = await import('html2pdf.js')
    await html2pdf().set({
      margin: 0,
      filename: `${contract.contract_number}_${(contract.party_b_company || contract.party_b_name || 'contract').replace(/[^a-zA-Z0-9]/g, '_')}.pdf`,
      html2canvas: { scale: 2, useCORS: true },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    }).from(html as unknown as HTMLElement).save()
  }

  return {
    generateHtml,
    downloadPdf,
    loadLogoDataUrl,
  }
}
