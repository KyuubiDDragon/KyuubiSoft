import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'

export interface DnsDomain {
  id: string
  user_id: string
  name: string
  provider: string
  provider_config: Record<string, any> | null
  external_zone_id: string | null
  notes: string | null
  record_count: number
  records?: DnsRecord[]
  created_at: string
  updated_at: string
}

export interface CloudflareZone {
  id: string
  name: string
  status: string
  name_servers: string[]
  plan: string
}

export interface DnsRecord {
  id: string
  domain_id: string
  external_id: string | null
  type: string
  name: string
  value: string
  ttl: number
  priority: number | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface PropagationResult {
  record_id: string
  query_name: string
  expected_type: string
  expected_value: string
  propagated: boolean
  dns_results: {
    host: string
    type: string
    value: string
    ttl: number | null
  }[]
  checked_at: string
}

export interface ZoneImportResult {
  imported_count: number
  records: DnsRecord[]
  errors: string[]
}

export const useDnsStore = defineStore('dns', () => {
  const uiStore = useUiStore()

  // State
  const domains = ref<DnsDomain[]>([])
  const currentDomain = ref<DnsDomain | null>(null)
  const loading = ref<boolean>(false)
  const recordsLoading = ref<boolean>(false)

  // Actions
  async function fetchDomains(): Promise<void> {
    loading.value = true
    try {
      const response = await api.get('/api/v1/dns/domains')
      domains.value = response.data.data || []
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Domains')
    } finally {
      loading.value = false
    }
  }

  async function createDomain(data: {
    name: string
    provider?: string
    provider_config?: Record<string, any> | null
    notes?: string
  }): Promise<DnsDomain | null> {
    try {
      const response = await api.post('/api/v1/dns/domains', data)
      const domain = response.data.data
      domains.value.push(domain)
      uiStore.showSuccess('Domain erstellt')
      return domain
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Erstellen der Domain'
      uiStore.showError(msg)
      return null
    }
  }

  async function fetchDomain(id: string): Promise<DnsDomain | null> {
    recordsLoading.value = true
    try {
      const response = await api.get(`/api/v1/dns/domains/${id}`)
      const domain = response.data.data
      currentDomain.value = domain
      // Update in the domains list as well
      const idx = domains.value.findIndex((d) => d.id === id)
      if (idx !== -1) {
        domains.value[idx] = { ...domains.value[idx], ...domain }
      }
      return domain
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Domain')
      return null
    } finally {
      recordsLoading.value = false
    }
  }

  async function updateDomain(
    id: string,
    data: Partial<{
      name: string
      provider: string
      provider_config: Record<string, any> | null
      notes: string | null
    }>
  ): Promise<DnsDomain | null> {
    try {
      const response = await api.put(`/api/v1/dns/domains/${id}`, data)
      const updated = response.data.data
      const idx = domains.value.findIndex((d) => d.id === id)
      if (idx !== -1) {
        domains.value[idx] = { ...domains.value[idx], ...updated }
      }
      if (currentDomain.value?.id === id) {
        currentDomain.value = { ...currentDomain.value, ...updated }
      }
      uiStore.showSuccess('Domain aktualisiert')
      return updated
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Aktualisieren der Domain'
      uiStore.showError(msg)
      return null
    }
  }

  async function deleteDomain(id: string): Promise<boolean> {
    try {
      await api.delete(`/api/v1/dns/domains/${id}`)
      domains.value = domains.value.filter((d) => d.id !== id)
      if (currentDomain.value?.id === id) {
        currentDomain.value = null
      }
      uiStore.showSuccess('Domain geloescht')
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Loeschen der Domain')
      return false
    }
  }

  async function fetchRecords(domainId: string): Promise<DnsRecord[]> {
    recordsLoading.value = true
    try {
      const response = await api.get(`/api/v1/dns/domains/${domainId}/records`)
      const records = response.data.data || []
      // Update the domain's records in the list
      const idx = domains.value.findIndex((d) => d.id === domainId)
      if (idx !== -1) {
        domains.value[idx].records = records
      }
      if (currentDomain.value?.id === domainId) {
        currentDomain.value.records = records
      }
      return records
    } catch (error) {
      uiStore.showError('Fehler beim Laden der DNS-Records')
      return []
    } finally {
      recordsLoading.value = false
    }
  }

  async function createRecord(
    domainId: string,
    data: {
      type: string
      name: string
      value: string
      ttl?: number
      priority?: number | null
      notes?: string
    }
  ): Promise<DnsRecord | null> {
    try {
      const response = await api.post(`/api/v1/dns/domains/${domainId}/records`, data)
      const record = response.data.data
      // Add to domain's records
      const idx = domains.value.findIndex((d) => d.id === domainId)
      if (idx !== -1) {
        if (!domains.value[idx].records) {
          domains.value[idx].records = []
        }
        domains.value[idx].records!.push(record)
        domains.value[idx].record_count++
      }
      if (currentDomain.value?.id === domainId) {
        if (!currentDomain.value.records) {
          currentDomain.value.records = []
        }
        currentDomain.value.records.push(record)
        currentDomain.value.record_count++
      }
      uiStore.showSuccess('DNS-Record erstellt')
      return record
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Erstellen des DNS-Records'
      uiStore.showError(msg)
      return null
    }
  }

  async function updateRecord(
    recordId: string,
    data: Partial<{
      type: string
      name: string
      value: string
      ttl: number
      priority: number | null
      notes: string | null
    }>
  ): Promise<DnsRecord | null> {
    try {
      const response = await api.put(`/api/v1/dns/records/${recordId}`, data)
      const updated = response.data.data
      // Update in domain records
      for (const domain of domains.value) {
        if (domain.records) {
          const rIdx = domain.records.findIndex((r) => r.id === recordId)
          if (rIdx !== -1) {
            domain.records[rIdx] = updated
            break
          }
        }
      }
      if (currentDomain.value?.records) {
        const rIdx = currentDomain.value.records.findIndex((r) => r.id === recordId)
        if (rIdx !== -1) {
          currentDomain.value.records[rIdx] = updated
        }
      }
      uiStore.showSuccess('DNS-Record aktualisiert')
      return updated
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Aktualisieren des DNS-Records'
      uiStore.showError(msg)
      return null
    }
  }

  async function deleteRecord(recordId: string, domainId: string): Promise<boolean> {
    try {
      await api.delete(`/api/v1/dns/records/${recordId}`)
      // Remove from domain records
      const idx = domains.value.findIndex((d) => d.id === domainId)
      if (idx !== -1) {
        if (domains.value[idx].records) {
          domains.value[idx].records = domains.value[idx].records!.filter((r) => r.id !== recordId)
        }
        domains.value[idx].record_count = Math.max(0, domains.value[idx].record_count - 1)
      }
      if (currentDomain.value?.id === domainId && currentDomain.value.records) {
        currentDomain.value.records = currentDomain.value.records.filter((r) => r.id !== recordId)
        currentDomain.value.record_count = Math.max(0, currentDomain.value.record_count - 1)
      }
      uiStore.showSuccess('DNS-Record geloescht')
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Loeschen des DNS-Records')
      return false
    }
  }

  async function checkPropagation(recordId: string): Promise<PropagationResult | null> {
    try {
      const response = await api.post(`/api/v1/dns/records/${recordId}/propagation`)
      return response.data.data
    } catch (error) {
      uiStore.showError('Fehler bei der DNS-Propagationspruefung')
      return null
    }
  }

  async function exportZone(domainId: string): Promise<string | null> {
    try {
      const response = await api.get(`/api/v1/dns/domains/${domainId}/export`)
      return response.data.data?.zone_file || null
    } catch (error) {
      uiStore.showError('Fehler beim Exportieren der Zone-Datei')
      return null
    }
  }

  async function importZone(domainId: string, zoneContent: string): Promise<ZoneImportResult | null> {
    try {
      const response = await api.post(`/api/v1/dns/domains/${domainId}/import`, { zone_content: zoneContent })
      const result = response.data.data
      if (result.imported_count > 0) {
        uiStore.showSuccess(`${result.imported_count} Records importiert`)
        // Refresh records
        await fetchRecords(domainId)
        // Update domain record count
        const idx = domains.value.findIndex((d) => d.id === domainId)
        if (idx !== -1) {
          domains.value[idx].record_count += result.imported_count
        }
      }
      if (result.errors.length > 0) {
        uiStore.showError(`${result.errors.length} Fehler beim Import`)
      }
      return result
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Importieren der Zone-Datei'
      uiStore.showError(msg)
      return null
    }
  }

  // ==================== Cloudflare Actions ====================

  async function verifyCloudflareToken(apiToken: string): Promise<boolean> {
    try {
      await api.post('/api/v1/dns/cloudflare/verify', { api_token: apiToken })
      uiStore.showSuccess('Cloudflare-Token ist gueltig')
      return true
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Token-Validierung fehlgeschlagen'
      uiStore.showError(msg)
      return false
    }
  }

  async function listCloudflareZones(apiToken: string): Promise<CloudflareZone[]> {
    try {
      const response = await api.post('/api/v1/dns/cloudflare/zones', { api_token: apiToken })
      return response.data.data?.items || []
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Laden der Cloudflare-Zonen'
      uiStore.showError(msg)
      return []
    }
  }

  async function importCloudflareZone(apiToken: string, zoneId: string, zoneName: string): Promise<DnsDomain | null> {
    try {
      const response = await api.post('/api/v1/dns/cloudflare/import', {
        api_token: apiToken,
        zone_id: zoneId,
        zone_name: zoneName,
      })
      const result = response.data.data
      if (result?.domain) {
        domains.value.push(result.domain)
        uiStore.showSuccess(`${result.imported_count} Records von Cloudflare importiert`)
        return result.domain
      }
      return null
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Cloudflare-Import fehlgeschlagen'
      uiStore.showError(msg)
      return null
    }
  }

  async function syncProvider(domainId: string): Promise<{ added: number; updated: number } | null> {
    try {
      const response = await api.post(`/api/v1/dns/domains/${domainId}/sync-provider`)
      const result = response.data.data
      uiStore.showSuccess(response.data.message || 'Sync abgeschlossen')
      // Refresh records
      await fetchDomain(domainId)
      return result
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Sync fehlgeschlagen'
      uiStore.showError(msg)
      return null
    }
  }

  async function pushProvider(domainId: string): Promise<{ created: number; updated: number; errors: string[] } | null> {
    try {
      const response = await api.post(`/api/v1/dns/domains/${domainId}/push-provider`)
      const result = response.data.data
      if (result.errors?.length > 0) {
        uiStore.showError(`${result.errors.length} Fehler beim Push`)
      } else {
        uiStore.showSuccess(response.data.message || 'Push abgeschlossen')
      }
      // Refresh records to get updated external_ids
      await fetchDomain(domainId)
      return result
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Push fehlgeschlagen'
      uiStore.showError(msg)
      return null
    }
  }

  return {
    // State
    domains,
    currentDomain,
    loading,
    recordsLoading,

    // Actions
    fetchDomains,
    createDomain,
    fetchDomain,
    updateDomain,
    deleteDomain,
    fetchRecords,
    createRecord,
    updateRecord,
    deleteRecord,
    checkPropagation,
    exportZone,
    importZone,

    // Cloudflare
    verifyCloudflareToken,
    listCloudflareZones,
    importCloudflareZone,
    syncProvider,
    pushProvider,
  }
})
