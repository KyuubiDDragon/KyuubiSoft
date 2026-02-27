<script setup>
import { ref, computed, watch } from 'vue'
import {
  ClipboardIcon,
  PlayIcon,
  BookmarkIcon,
  ServerIcon,
  TableCellsIcon,
  CommandLineIcon,
  CogIcon,
  DocumentTextIcon,
  ArrowDownTrayIcon,
  PlusIcon,
  TrashIcon,
  CheckIcon,
  InformationCircleIcon,
  MagnifyingGlassIcon,
  ChevronDownIcon,
  ChevronRightIcon
} from '@heroicons/vue/24/outline'

// ============================================
// STATE
// ============================================

const activeTab = ref('builder')
const copied = ref(false)
const searchSchema = ref('')
const searchTemplates = ref('')
const expandedEntities = ref([])
const expandedCategories = ref(['alerts', 'nodes', 'interfaces'])

// Query Builder State
const selectedEntity = ref('Orion.Nodes')
const selectedColumns = ref(['NodeID', 'Caption', 'IP_Address', 'Status', 'StatusDescription'])
const whereConditions = ref([])
const orderBy = ref('')
const orderDirection = ref('ASC')
const topCount = ref('')
const distinctResults = ref(false)

// Connection Manager State
const connections = ref([
  { id: 1, name: 'Production Orion', hostname: 'orion.company.com', username: 'admin', isDefault: true },
])
const newConnection = ref({ name: '', hostname: '', username: '', password: '' })
const showAddConnection = ref(false)
const selectedConnection = ref(1)

// Verb Builder State
const selectedVerb = ref('Unmanage')
const verbParams = ref({
  nodeIds: '',
  startTime: '',
  endTime: '',
  alertIds: '',
  note: '',
  propertyName: '',
  propertyValue: ''
})

// Result Viewer State
const queryResults = ref([])
const showResults = ref(false)

// ============================================
// SWQL SCHEMA - Entities & Properties
// ============================================

const swqlSchema = {
  'Orion.Nodes': {
    description: 'Alle überwachten Nodes (Server, Router, Switches, etc.)',
    properties: [
      { name: 'NodeID', type: 'int', description: 'Eindeutige Node-ID' },
      { name: 'Caption', type: 'string', description: 'Anzeigename des Nodes' },
      { name: 'IP_Address', type: 'string', description: 'IP-Adresse' },
      { name: 'IPAddress', type: 'string', description: 'IP-Adresse (alternativ)' },
      { name: 'DNS', type: 'string', description: 'DNS-Name' },
      { name: 'Status', type: 'int', description: 'Status-Code (1=Up, 2=Down, etc.)' },
      { name: 'StatusDescription', type: 'string', description: 'Status als Text' },
      { name: 'StatusIcon', type: 'string', description: 'Status-Icon Pfad' },
      { name: 'Vendor', type: 'string', description: 'Hersteller (Cisco, HP, etc.)' },
      { name: 'MachineType', type: 'string', description: 'Gerätetyp' },
      { name: 'SysName', type: 'string', description: 'SNMP sysName' },
      { name: 'Description', type: 'string', description: 'Beschreibung' },
      { name: 'Location', type: 'string', description: 'SNMP Location' },
      { name: 'Contact', type: 'string', description: 'SNMP Contact' },
      { name: 'IOSVersion', type: 'string', description: 'OS/Firmware Version' },
      { name: 'ObjectSubType', type: 'string', description: 'Subtyp (SNMP, ICMP, WMI, Agent)' },
      { name: 'PercentLoss', type: 'float', description: 'Paketverlust in %' },
      { name: 'AvgResponseTime', type: 'int', description: 'Durchschnittliche Antwortzeit (ms)' },
      { name: 'ResponseTime', type: 'int', description: 'Aktuelle Antwortzeit (ms)' },
      { name: 'MinResponseTime', type: 'int', description: 'Minimale Antwortzeit (ms)' },
      { name: 'MaxResponseTime', type: 'int', description: 'Maximale Antwortzeit (ms)' },
      { name: 'CPULoad', type: 'int', description: 'CPU-Auslastung in %' },
      { name: 'PercentMemoryUsed', type: 'float', description: 'RAM-Auslastung in %' },
      { name: 'TotalMemory', type: 'float', description: 'Gesamter RAM' },
      { name: 'LastBoot', type: 'datetime', description: 'Letzter Neustart' },
      { name: 'LastSync', type: 'datetime', description: 'Letzte Synchronisation' },
      { name: 'NextPoll', type: 'datetime', description: 'Nächste Abfrage' },
      { name: 'NextRediscovery', type: 'datetime', description: 'Nächste Rediscovery' },
      { name: 'PollInterval', type: 'int', description: 'Poll-Intervall in Sekunden' },
      { name: 'StatCollection', type: 'int', description: 'Statistik-Intervall' },
      { name: 'RediscoveryInterval', type: 'int', description: 'Rediscovery-Intervall' },
      { name: 'Unmanaged', type: 'bool', description: 'Ist Node unmanaged?' },
      { name: 'UnManageFrom', type: 'datetime', description: 'Unmanage Start' },
      { name: 'UnManageUntil', type: 'datetime', description: 'Unmanage Ende' },
      { name: 'Uri', type: 'string', description: 'SWIS URI des Nodes' },
      { name: 'DetailsUrl', type: 'string', description: 'URL zur Node-Detail-Seite' },
      { name: 'EntityType', type: 'string', description: 'Entity-Typ' },
      { name: 'EngineID', type: 'int', description: 'Polling Engine ID' },
      { name: 'CustomProperties.*', type: 'various', description: 'Custom Properties' }
    ],
    verbs: ['Unmanage', 'Remanage', 'PollNow', 'Rediscover', 'SetCustomProperty']
  },
  'Orion.AlertActive': {
    description: 'Aktuell aktive Alerts',
    properties: [
      { name: 'AlertActiveID', type: 'int', description: 'Eindeutige Alert-ID' },
      { name: 'AlertObjectID', type: 'int', description: 'Alert Object ID' },
      { name: 'TriggeredDateTime', type: 'datetime', description: 'Auslöse-Zeitpunkt' },
      { name: 'TriggeredMessage', type: 'string', description: 'Alert-Nachricht' },
      { name: 'Acknowledged', type: 'bool', description: 'Bestätigt?' },
      { name: 'AcknowledgedBy', type: 'string', description: 'Bestätigt von' },
      { name: 'AcknowledgedDateTime', type: 'datetime', description: 'Bestätigt um' },
      { name: 'AcknowledgedNote', type: 'string', description: 'Bestätigungs-Notiz' },
      { name: 'NumberOfNotes', type: 'int', description: 'Anzahl Notizen' },
      { name: 'LastExecutedEscalationLevel', type: 'int', description: 'Letztes Eskalations-Level' },
      { name: 'AlertNote', type: 'string', description: 'Alert-Notiz' }
    ],
    verbs: ['Acknowledge', 'ClearAlert', 'AppendNote']
  },
  'Orion.AlertConfigurations': {
    description: 'Alert-Definitionen und Konfigurationen',
    properties: [
      { name: 'AlertID', type: 'int', description: 'Alert Definition ID' },
      { name: 'Name', type: 'string', description: 'Alert-Name' },
      { name: 'Description', type: 'string', description: 'Beschreibung' },
      { name: 'Enabled', type: 'bool', description: 'Aktiviert?' },
      { name: 'Severity', type: 'int', description: 'Schweregrad (0-3)' },
      { name: 'ObjectType', type: 'string', description: 'Objekt-Typ' },
      { name: 'LastEdit', type: 'datetime', description: 'Letzte Bearbeitung' },
      { name: 'CreatedBy', type: 'string', description: 'Erstellt von' },
      { name: 'Category', type: 'string', description: 'Kategorie' }
    ],
    verbs: ['Enable', 'Disable', 'Export']
  },
  'Orion.AlertHistory': {
    description: 'Alert-Historie und Events',
    properties: [
      { name: 'AlertHistoryID', type: 'int', description: 'Historie-ID' },
      { name: 'AlertActiveID', type: 'int', description: 'Aktive Alert-ID' },
      { name: 'EventType', type: 'int', description: 'Event-Typ' },
      { name: 'Message', type: 'string', description: 'Event-Nachricht' },
      { name: 'TimeStamp', type: 'datetime', description: 'Zeitstempel' },
      { name: 'AccountID', type: 'string', description: 'Benutzer-Account' }
    ],
    verbs: []
  },
  'Orion.AlertObjects': {
    description: 'Objekte die Alerts ausgelöst haben',
    properties: [
      { name: 'AlertObjectID', type: 'int', description: 'Alert Object ID' },
      { name: 'AlertID', type: 'int', description: 'Alert Definition ID' },
      { name: 'EntityUri', type: 'string', description: 'Entity URI' },
      { name: 'EntityType', type: 'string', description: 'Entity-Typ' },
      { name: 'EntityCaption', type: 'string', description: 'Entity-Name' },
      { name: 'EntityDetailsUrl', type: 'string', description: 'Details-URL' },
      { name: 'EntityNetObjectId', type: 'string', description: 'Net Object ID' },
      { name: 'RelatedNodeUri', type: 'string', description: 'Related Node URI' },
      { name: 'RelatedNodeCaption', type: 'string', description: 'Related Node Name' },
      { name: 'TriggeredCount', type: 'int', description: 'Anzahl Auslösungen' },
      { name: 'LastTriggeredDateTime', type: 'datetime', description: 'Letzte Auslösung' }
    ],
    verbs: []
  },
  'Orion.AlertSuppression': {
    description: 'Alert-Unterdrückung (Maintenance Windows)',
    properties: [
      { name: 'AlertSuppressionID', type: 'int', description: 'Suppression ID' },
      { name: 'EntityUri', type: 'string', description: 'Entity URI' },
      { name: 'SuppressFrom', type: 'datetime', description: 'Unterdrückung von' },
      { name: 'SuppressUntil', type: 'datetime', description: 'Unterdrückung bis' }
    ],
    verbs: ['SuppressAlerts', 'ResumeAlerts']
  },
  'Orion.NPM.Interfaces': {
    description: 'Netzwerk-Interfaces',
    properties: [
      { name: 'InterfaceID', type: 'int', description: 'Interface-ID' },
      { name: 'NodeID', type: 'int', description: 'Zugehörige Node-ID' },
      { name: 'InterfaceName', type: 'string', description: 'Interface-Name' },
      { name: 'InterfaceAlias', type: 'string', description: 'Interface-Alias' },
      { name: 'Caption', type: 'string', description: 'Anzeigename' },
      { name: 'FullName', type: 'string', description: 'Vollständiger Name' },
      { name: 'Status', type: 'int', description: 'Status-Code' },
      { name: 'StatusDescription', type: 'string', description: 'Status als Text' },
      { name: 'OperStatus', type: 'int', description: 'Operational Status' },
      { name: 'AdminStatus', type: 'int', description: 'Admin Status' },
      { name: 'Speed', type: 'float', description: 'Geschwindigkeit (bps)' },
      { name: 'MTU', type: 'int', description: 'MTU-Größe' },
      { name: 'TypeName', type: 'string', description: 'Interface-Typ' },
      { name: 'TypeDescription', type: 'string', description: 'Typ-Beschreibung' },
      { name: 'PhysicalAddress', type: 'string', description: 'MAC-Adresse' },
      { name: 'InBandwidth', type: 'float', description: 'Eingehende Bandbreite (%)' },
      { name: 'OutBandwidth', type: 'float', description: 'Ausgehende Bandbreite (%)' },
      { name: 'InPercentUtil', type: 'float', description: 'Eingehende Auslastung (%)' },
      { name: 'OutPercentUtil', type: 'float', description: 'Ausgehende Auslastung (%)' },
      { name: 'InBps', type: 'float', description: 'Eingehend (bits/sec)' },
      { name: 'OutBps', type: 'float', description: 'Ausgehend (bits/sec)' },
      { name: 'InPps', type: 'float', description: 'Eingehend (packets/sec)' },
      { name: 'OutPps', type: 'float', description: 'Ausgehend (packets/sec)' },
      { name: 'InDiscardsThisHour', type: 'int', description: 'Discards In (Stunde)' },
      { name: 'OutDiscardsThisHour', type: 'int', description: 'Discards Out (Stunde)' },
      { name: 'InErrorsThisHour', type: 'int', description: 'Errors In (Stunde)' },
      { name: 'OutErrorsThisHour', type: 'int', description: 'Errors Out (Stunde)' },
      { name: 'Unmanaged', type: 'bool', description: 'Ist unmanaged?' },
      { name: 'UnManageFrom', type: 'datetime', description: 'Unmanage Start' },
      { name: 'UnManageUntil', type: 'datetime', description: 'Unmanage Ende' },
      { name: 'Uri', type: 'string', description: 'SWIS URI' }
    ],
    verbs: ['Unmanage', 'Remanage']
  },
  'Orion.Volumes': {
    description: 'Disk Volumes (Festplatten)',
    properties: [
      { name: 'VolumeID', type: 'int', description: 'Volume-ID' },
      { name: 'NodeID', type: 'int', description: 'Node-ID' },
      { name: 'Caption', type: 'string', description: 'Volume-Name' },
      { name: 'VolumeDescription', type: 'string', description: 'Beschreibung' },
      { name: 'VolumeType', type: 'string', description: 'Volume-Typ' },
      { name: 'VolumeTypeIcon', type: 'string', description: 'Typ-Icon' },
      { name: 'Status', type: 'int', description: 'Status-Code' },
      { name: 'StatusDescription', type: 'string', description: 'Status als Text' },
      { name: 'VolumeSize', type: 'float', description: 'Gesamtgröße (Bytes)' },
      { name: 'VolumeSpaceUsed', type: 'float', description: 'Belegt (Bytes)' },
      { name: 'VolumeSpaceAvailable', type: 'float', description: 'Verfügbar (Bytes)' },
      { name: 'VolumePercentUsed', type: 'float', description: 'Belegt (%)' },
      { name: 'VolumeAllocationFailuresThisHour', type: 'int', description: 'Alloc Failures' },
      { name: 'Unmanaged', type: 'bool', description: 'Ist unmanaged?' },
      { name: 'Uri', type: 'string', description: 'SWIS URI' }
    ],
    verbs: ['Unmanage', 'Remanage']
  },
  'Orion.CPUMultiLoad': {
    description: 'CPU-Load für Multi-CPU-Systeme',
    properties: [
      { name: 'NodeID', type: 'int', description: 'Node-ID' },
      { name: 'CPUIndex', type: 'int', description: 'CPU-Index' },
      { name: 'AvgLoad', type: 'int', description: 'Durchschnittliche Last (%)' },
      { name: 'MinLoad', type: 'int', description: 'Minimale Last (%)' },
      { name: 'MaxLoad', type: 'int', description: 'Maximale Last (%)' },
      { name: 'TimeStampUTC', type: 'datetime', description: 'Zeitstempel UTC' }
    ],
    verbs: []
  },
  'Orion.Events': {
    description: 'Orion Events und Logs',
    properties: [
      { name: 'EventID', type: 'int', description: 'Event-ID' },
      { name: 'EventTime', type: 'datetime', description: 'Event-Zeit' },
      { name: 'NetworkNode', type: 'int', description: 'Node-ID' },
      { name: 'EventType', type: 'int', description: 'Event-Typ' },
      { name: 'Message', type: 'string', description: 'Event-Nachricht' },
      { name: 'Acknowledged', type: 'bool', description: 'Bestätigt?' },
      { name: 'NetObjectID', type: 'string', description: 'Net Object ID' },
      { name: 'NetObjectType', type: 'string', description: 'Net Object Typ' }
    ],
    verbs: ['Acknowledge']
  },
  'Orion.Pollers': {
    description: 'Poller-Konfigurationen',
    properties: [
      { name: 'PollerID', type: 'int', description: 'Poller-ID' },
      { name: 'PollerType', type: 'string', description: 'Poller-Typ' },
      { name: 'NetObject', type: 'string', description: 'Net Object' },
      { name: 'NetObjectType', type: 'string', description: 'Net Object Typ' },
      { name: 'NetObjectID', type: 'int', description: 'Net Object ID' },
      { name: 'Enabled', type: 'bool', description: 'Aktiviert?' }
    ],
    verbs: ['Enable', 'Disable']
  },
  'Orion.Groups': {
    description: 'Orion Gruppen (Container)',
    properties: [
      { name: 'ContainerID', type: 'int', description: 'Container-ID' },
      { name: 'Name', type: 'string', description: 'Gruppen-Name' },
      { name: 'Description', type: 'string', description: 'Beschreibung' },
      { name: 'Owner', type: 'string', description: 'Besitzer' },
      { name: 'Frequency', type: 'int', description: 'Poll-Frequenz' },
      { name: 'StatusCalculator', type: 'int', description: 'Status-Berechnung' },
      { name: 'RollupType', type: 'int', description: 'Rollup-Typ' },
      { name: 'Status', type: 'int', description: 'Status-Code' },
      { name: 'StatusDescription', type: 'string', description: 'Status als Text' },
      { name: 'Uri', type: 'string', description: 'SWIS URI' }
    ],
    verbs: []
  },
  'Orion.Engines': {
    description: 'Orion Polling Engines',
    properties: [
      { name: 'EngineID', type: 'int', description: 'Engine-ID' },
      { name: 'ServerName', type: 'string', description: 'Server-Name' },
      { name: 'IP', type: 'string', description: 'IP-Adresse' },
      { name: 'ServerType', type: 'string', description: 'Server-Typ' },
      { name: 'PollingCompletion', type: 'float', description: 'Polling Completion (%)' },
      { name: 'Elements', type: 'int', description: 'Anzahl Elemente' },
      { name: 'Nodes', type: 'int', description: 'Anzahl Nodes' },
      { name: 'Interfaces', type: 'int', description: 'Anzahl Interfaces' },
      { name: 'Volumes', type: 'int', description: 'Anzahl Volumes' }
    ],
    verbs: []
  },
  'Orion.Accounts': {
    description: 'Orion Benutzer-Accounts',
    properties: [
      { name: 'AccountID', type: 'string', description: 'Account-ID' },
      { name: 'Enabled', type: 'bool', description: 'Aktiviert?' },
      { name: 'AllowAdmin', type: 'bool', description: 'Admin-Rechte?' },
      { name: 'LastLogin', type: 'datetime', description: 'Letzte Anmeldung' },
      { name: 'AccountType', type: 'int', description: 'Account-Typ' },
      { name: 'AccountSID', type: 'string', description: 'Account SID' }
    ],
    verbs: []
  },
  'Orion.NodesCustomProperties': {
    description: 'Custom Properties für Nodes',
    properties: [
      { name: 'NodeID', type: 'int', description: 'Node-ID' },
      { name: 'City', type: 'string', description: 'Stadt (Standard CP)' },
      { name: 'Department', type: 'string', description: 'Abteilung (Standard CP)' },
      { name: 'Comments', type: 'string', description: 'Kommentare (Standard CP)' }
    ],
    verbs: []
  },
  'Orion.Audit': {
    description: 'Audit-Logs',
    properties: [
      { name: 'AuditEventID', type: 'int', description: 'Audit Event ID' },
      { name: 'TimeLoggedUtc', type: 'datetime', description: 'Zeitpunkt UTC' },
      { name: 'AccountID', type: 'string', description: 'Account' },
      { name: 'ActionTypeID', type: 'int', description: 'Action-Typ' },
      { name: 'AuditEventMessage', type: 'string', description: 'Event-Nachricht' },
      { name: 'NetworkNode', type: 'int', description: 'Node-ID' },
      { name: 'NetObjectType', type: 'string', description: 'Object-Typ' }
    ],
    verbs: []
  },
  'Orion.VIM.VirtualMachines': {
    description: 'Virtuelle Maschinen (VMware/Hyper-V)',
    properties: [
      { name: 'VirtualMachineID', type: 'int', description: 'VM-ID' },
      { name: 'NodeID', type: 'int', description: 'Node-ID' },
      { name: 'HostID', type: 'int', description: 'Host-ID' },
      { name: 'Name', type: 'string', description: 'VM-Name' },
      { name: 'IPAddress', type: 'string', description: 'IP-Adresse' },
      { name: 'GuestState', type: 'string', description: 'Guest-Status' },
      { name: 'PowerState', type: 'string', description: 'Power-Status' },
      { name: 'CPUCount', type: 'int', description: 'CPU-Anzahl' },
      { name: 'MemoryConfigured', type: 'float', description: 'Konfigurierter RAM' },
      { name: 'CpuUsageMHz', type: 'float', description: 'CPU-Nutzung (MHz)' },
      { name: 'MemUsage', type: 'float', description: 'RAM-Nutzung (%)' },
      { name: 'VMwareToolsStatus', type: 'string', description: 'VMware Tools Status' }
    ],
    verbs: []
  },
  'NCM.Nodes': {
    description: 'NCM überwachte Nodes',
    properties: [
      { name: 'NodeID', type: 'int', description: 'Node-ID' },
      { name: 'CoreNodeID', type: 'int', description: 'Core Node-ID' },
      { name: 'NodeCaption', type: 'string', description: 'Node-Name' },
      { name: 'AgentIP', type: 'string', description: 'Agent IP' },
      { name: 'ConnectionProfile', type: 'int', description: 'Connection Profile' },
      { name: 'LastConfigDownload', type: 'datetime', description: 'Letzter Config Download' },
      { name: 'LastInventory', type: 'datetime', description: 'Letztes Inventory' },
      { name: 'ConfigStatus', type: 'int', description: 'Config-Status' }
    ],
    verbs: ['DownloadConfig', 'UploadConfig', 'ExecuteScript']
  },
  'IPAM.IPNode': {
    description: 'IPAM IP-Adressen',
    properties: [
      { name: 'IPNodeId', type: 'int', description: 'IP Node ID' },
      { name: 'IPAddress', type: 'string', description: 'IP-Adresse' },
      { name: 'SubnetId', type: 'int', description: 'Subnet-ID' },
      { name: 'Status', type: 'int', description: 'Status-Code' },
      { name: 'StatusName', type: 'string', description: 'Status-Name' },
      { name: 'DnsBackward', type: 'string', description: 'Reverse DNS' },
      { name: 'MAC', type: 'string', description: 'MAC-Adresse' },
      { name: 'SystemName', type: 'string', description: 'System-Name' },
      { name: 'Comments', type: 'string', description: 'Kommentare' },
      { name: 'LastSync', type: 'datetime', description: 'Letzte Sync' }
    ],
    verbs: []
  },
  'IPAM.Subnet': {
    description: 'IPAM Subnets',
    properties: [
      { name: 'SubnetId', type: 'int', description: 'Subnet-ID' },
      { name: 'Address', type: 'string', description: 'Netzwerk-Adresse' },
      { name: 'CIDR', type: 'int', description: 'CIDR-Notation' },
      { name: 'FriendlyName', type: 'string', description: 'Anzeigename' },
      { name: 'Description', type: 'string', description: 'Beschreibung' },
      { name: 'VLAN', type: 'string', description: 'VLAN' },
      { name: 'Location', type: 'string', description: 'Standort' },
      { name: 'UsedCount', type: 'int', description: 'Genutzte IPs' },
      { name: 'AvailableCount', type: 'int', description: 'Verfügbare IPs' },
      { name: 'PercentUsed', type: 'float', description: 'Auslastung (%)' }
    ],
    verbs: []
  }
}

// ============================================
// QUERY TEMPLATES
// ============================================

const queryTemplates = {
  alerts: {
    name: 'Alerts',
    icon: '🚨',
    templates: [
      {
        name: 'Alle aktiven Alerts',
        description: 'Zeigt alle aktuell aktiven Alerts',
        query: `SELECT AlertActiveID, AlertObjectID, TriggeredDateTime, TriggeredMessage, Acknowledged, AcknowledgedBy
FROM Orion.AlertActive
ORDER BY TriggeredDateTime DESC`
      },
      {
        name: 'Unbestätigte Alerts',
        description: 'Alerts die noch nicht acknowledged wurden',
        query: `SELECT aa.AlertActiveID, aa.TriggeredDateTime, aa.TriggeredMessage, ao.EntityCaption, ao.RelatedNodeCaption
FROM Orion.AlertActive aa
INNER JOIN Orion.AlertObjects ao ON aa.AlertObjectID = ao.AlertObjectID
WHERE aa.Acknowledged = FALSE
ORDER BY aa.TriggeredDateTime DESC`
      },
      {
        name: 'Alerts nach Severity',
        description: 'Alerts gruppiert nach Schweregrad',
        query: `SELECT ac.Severity,
  CASE ac.Severity
    WHEN 0 THEN 'Information'
    WHEN 1 THEN 'Warning'
    WHEN 2 THEN 'Critical'
    WHEN 3 THEN 'Serious'
  END AS SeverityName,
  COUNT(*) AS AlertCount
FROM Orion.AlertActive aa
INNER JOIN Orion.AlertObjects ao ON aa.AlertObjectID = ao.AlertObjectID
INNER JOIN Orion.AlertConfigurations ac ON ao.AlertID = ac.AlertID
GROUP BY ac.Severity
ORDER BY ac.Severity DESC`
      },
      {
        name: 'Alerts der letzten 24 Stunden',
        description: 'Alle Alerts der letzten 24 Stunden',
        query: `SELECT aa.AlertActiveID, aa.TriggeredDateTime, aa.TriggeredMessage, aa.Acknowledged, ao.EntityCaption
FROM Orion.AlertActive aa
INNER JOIN Orion.AlertObjects ao ON aa.AlertObjectID = ao.AlertObjectID
WHERE aa.TriggeredDateTime > ADDDAY(-1, GETUTCDATE())
ORDER BY aa.TriggeredDateTime DESC`
      },
      {
        name: 'Alert-Historie (letzte 7 Tage)',
        description: 'Alle Alert-Events der letzten Woche',
        query: `SELECT AlertHistoryID, AlertActiveID, EventType, Message, TimeStamp, AccountID
FROM Orion.AlertHistory
WHERE TimeStamp > ADDDAY(-7, GETUTCDATE())
ORDER BY TimeStamp DESC`
      },
      {
        name: 'Top 10 häufigste Alerts',
        description: 'Die am häufigsten ausgelösten Alert-Typen',
        query: `SELECT TOP 10 ac.Name, ac.Severity, COUNT(*) AS TriggerCount
FROM Orion.AlertActive aa
INNER JOIN Orion.AlertObjects ao ON aa.AlertObjectID = ao.AlertObjectID
INNER JOIN Orion.AlertConfigurations ac ON ao.AlertID = ac.AlertID
GROUP BY ac.Name, ac.Severity
ORDER BY TriggerCount DESC`
      },
      {
        name: 'Alert-Konfigurationen',
        description: 'Alle definierten Alert-Regeln',
        query: `SELECT AlertID, Name, Description, Enabled, Severity, ObjectType, LastEdit
FROM Orion.AlertConfigurations
ORDER BY Name`
      },
      {
        name: 'Deaktivierte Alerts',
        description: 'Alert-Regeln die deaktiviert sind',
        query: `SELECT AlertID, Name, Description, Severity, LastEdit
FROM Orion.AlertConfigurations
WHERE Enabled = FALSE
ORDER BY Name`
      },
      {
        name: 'Alert Suppression Status',
        description: 'Alle unterdrückten Alerts (Maintenance)',
        query: `SELECT EntityUri, SuppressFrom, SuppressUntil
FROM Orion.AlertSuppression
WHERE SuppressUntil > GETUTCDATE()
ORDER BY SuppressUntil`
      },
      {
        name: 'Alerts pro Node',
        description: 'Anzahl aktiver Alerts pro Node',
        query: `SELECT ao.RelatedNodeCaption AS NodeName, COUNT(*) AS AlertCount
FROM Orion.AlertActive aa
INNER JOIN Orion.AlertObjects ao ON aa.AlertObjectID = ao.AlertObjectID
WHERE ao.RelatedNodeCaption IS NOT NULL
GROUP BY ao.RelatedNodeCaption
ORDER BY AlertCount DESC`
      }
    ]
  },
  nodes: {
    name: 'Nodes',
    icon: '🖥️',
    templates: [
      {
        name: 'Alle Nodes',
        description: 'Komplette Node-Liste mit Status',
        query: `SELECT NodeID, Caption, IP_Address, Status, StatusDescription, Vendor, MachineType
FROM Orion.Nodes
ORDER BY Caption`
      },
      {
        name: 'Down Nodes',
        description: 'Alle Nodes die aktuell down sind',
        query: `SELECT NodeID, Caption, IP_Address, StatusDescription, Vendor, ResponseTime, PercentLoss
FROM Orion.Nodes
WHERE Status = 2
ORDER BY Caption`
      },
      {
        name: 'Warning Nodes',
        description: 'Nodes im Warning-Status',
        query: `SELECT NodeID, Caption, IP_Address, StatusDescription, Vendor, CPULoad, PercentMemoryUsed
FROM Orion.Nodes
WHERE Status = 3
ORDER BY Caption`
      },
      {
        name: 'Unmanaged Nodes',
        description: 'Nodes die aktuell nicht überwacht werden',
        query: `SELECT NodeID, Caption, IP_Address, UnManageFrom, UnManageUntil
FROM Orion.Nodes
WHERE Unmanaged = TRUE
ORDER BY UnManageUntil`
      },
      {
        name: 'Nodes nach Vendor',
        description: 'Nodes gruppiert nach Hersteller',
        query: `SELECT Vendor, COUNT(*) AS NodeCount
FROM Orion.Nodes
GROUP BY Vendor
ORDER BY NodeCount DESC`
      },
      {
        name: 'CPU-kritische Nodes',
        description: 'Nodes mit hoher CPU-Auslastung (>80%)',
        query: `SELECT NodeID, Caption, IP_Address, CPULoad, Vendor
FROM Orion.Nodes
WHERE CPULoad > 80
ORDER BY CPULoad DESC`
      },
      {
        name: 'Memory-kritische Nodes',
        description: 'Nodes mit hoher RAM-Auslastung (>85%)',
        query: `SELECT NodeID, Caption, IP_Address, PercentMemoryUsed, TotalMemory, Vendor
FROM Orion.Nodes
WHERE PercentMemoryUsed > 85
ORDER BY PercentMemoryUsed DESC`
      },
      {
        name: 'Nodes mit Paketverlusten',
        description: 'Nodes mit Packet Loss > 0%',
        query: `SELECT NodeID, Caption, IP_Address, PercentLoss, ResponseTime, StatusDescription
FROM Orion.Nodes
WHERE PercentLoss > 0
ORDER BY PercentLoss DESC`
      },
      {
        name: 'Langsame Nodes',
        description: 'Nodes mit hoher Response Time (>100ms)',
        query: `SELECT NodeID, Caption, IP_Address, ResponseTime, AvgResponseTime, MaxResponseTime
FROM Orion.Nodes
WHERE ResponseTime > 100
ORDER BY ResponseTime DESC`
      },
      {
        name: 'Kürzlich neu gestartet',
        description: 'Nodes die in den letzten 24h gestartet wurden',
        query: `SELECT NodeID, Caption, IP_Address, LastBoot, Vendor
FROM Orion.Nodes
WHERE LastBoot > ADDDAY(-1, GETUTCDATE())
ORDER BY LastBoot DESC`
      },
      {
        name: 'Nodes ohne SNMP',
        description: 'Nodes die per ICMP überwacht werden',
        query: `SELECT NodeID, Caption, IP_Address, ObjectSubType, Status
FROM Orion.Nodes
WHERE ObjectSubType = 'ICMP'
ORDER BY Caption`
      },
      {
        name: 'Nodes per Polling Engine',
        description: 'Verteilung der Nodes auf Polling Engines',
        query: `SELECT e.ServerName, COUNT(n.NodeID) AS NodeCount
FROM Orion.Nodes n
INNER JOIN Orion.Engines e ON n.EngineID = e.EngineID
GROUP BY e.ServerName
ORDER BY NodeCount DESC`
      },
      {
        name: 'Node-Suche nach IP',
        description: 'Node anhand IP-Adresse finden (Parameter: @ip)',
        query: `SELECT NodeID, Caption, IP_Address, Status, StatusDescription, Vendor
FROM Orion.Nodes
WHERE IP_Address LIKE @ip`
      },
      {
        name: 'Node-Suche nach Name',
        description: 'Node anhand Name finden (Parameter: @name)',
        query: `SELECT NodeID, Caption, IP_Address, Status, StatusDescription, Vendor
FROM Orion.Nodes
WHERE Caption LIKE @name`
      },
      {
        name: 'Nodes mit Custom Property',
        description: 'Nodes nach Custom Property filtern',
        query: `SELECT n.NodeID, n.Caption, n.IP_Address, cp.City, cp.Department
FROM Orion.Nodes n
INNER JOIN Orion.NodesCustomProperties cp ON n.NodeID = cp.NodeID
WHERE cp.City IS NOT NULL
ORDER BY cp.City, n.Caption`
      }
    ]
  },
  interfaces: {
    name: 'Interfaces',
    icon: '🔌',
    templates: [
      {
        name: 'Alle Interfaces',
        description: 'Komplette Interface-Liste',
        query: `SELECT i.InterfaceID, i.Caption, i.Status, i.StatusDescription, i.Speed, n.Caption AS NodeName
FROM Orion.NPM.Interfaces i
INNER JOIN Orion.Nodes n ON i.NodeID = n.NodeID
ORDER BY n.Caption, i.Caption`
      },
      {
        name: 'Down Interfaces',
        description: 'Interfaces die aktuell down sind',
        query: `SELECT i.InterfaceID, i.Caption, i.StatusDescription, n.Caption AS NodeName, n.IP_Address
FROM Orion.NPM.Interfaces i
INNER JOIN Orion.Nodes n ON i.NodeID = n.NodeID
WHERE i.Status = 2
ORDER BY n.Caption`
      },
      {
        name: 'High Utilization (Inbound)',
        description: 'Interfaces mit hoher eingehender Auslastung (>70%)',
        query: `SELECT i.Caption, i.InPercentUtil, i.InBps, i.Speed, n.Caption AS NodeName
FROM Orion.NPM.Interfaces i
INNER JOIN Orion.Nodes n ON i.NodeID = n.NodeID
WHERE i.InPercentUtil > 70
ORDER BY i.InPercentUtil DESC`
      },
      {
        name: 'High Utilization (Outbound)',
        description: 'Interfaces mit hoher ausgehender Auslastung (>70%)',
        query: `SELECT i.Caption, i.OutPercentUtil, i.OutBps, i.Speed, n.Caption AS NodeName
FROM Orion.NPM.Interfaces i
INNER JOIN Orion.Nodes n ON i.NodeID = n.NodeID
WHERE i.OutPercentUtil > 70
ORDER BY i.OutPercentUtil DESC`
      },
      {
        name: 'Interfaces mit Errors',
        description: 'Interfaces mit Fehlern in der letzten Stunde',
        query: `SELECT i.Caption, i.InErrorsThisHour, i.OutErrorsThisHour, n.Caption AS NodeName
FROM Orion.NPM.Interfaces i
INNER JOIN Orion.Nodes n ON i.NodeID = n.NodeID
WHERE i.InErrorsThisHour > 0 OR i.OutErrorsThisHour > 0
ORDER BY (i.InErrorsThisHour + i.OutErrorsThisHour) DESC`
      },
      {
        name: 'Interfaces mit Discards',
        description: 'Interfaces mit Discards in der letzten Stunde',
        query: `SELECT i.Caption, i.InDiscardsThisHour, i.OutDiscardsThisHour, n.Caption AS NodeName
FROM Orion.NPM.Interfaces i
INNER JOIN Orion.Nodes n ON i.NodeID = n.NodeID
WHERE i.InDiscardsThisHour > 0 OR i.OutDiscardsThisHour > 0
ORDER BY (i.InDiscardsThisHour + i.OutDiscardsThisHour) DESC`
      },
      {
        name: 'Unmanaged Interfaces',
        description: 'Interfaces die nicht überwacht werden',
        query: `SELECT i.Caption, i.UnManageFrom, i.UnManageUntil, n.Caption AS NodeName
FROM Orion.NPM.Interfaces i
INNER JOIN Orion.Nodes n ON i.NodeID = n.NodeID
WHERE i.Unmanaged = TRUE
ORDER BY i.UnManageUntil`
      },
      {
        name: 'Interface-Typen Übersicht',
        description: 'Statistik nach Interface-Typ',
        query: `SELECT TypeName, COUNT(*) AS InterfaceCount
FROM Orion.NPM.Interfaces
GROUP BY TypeName
ORDER BY InterfaceCount DESC`
      },
      {
        name: 'Interfaces nach Geschwindigkeit',
        description: 'Interface-Anzahl nach Speed',
        query: `SELECT
  CASE
    WHEN Speed >= 10000000000 THEN '10 Gbps+'
    WHEN Speed >= 1000000000 THEN '1 Gbps'
    WHEN Speed >= 100000000 THEN '100 Mbps'
    WHEN Speed >= 10000000 THEN '10 Mbps'
    ELSE 'Unter 10 Mbps'
  END AS SpeedCategory,
  COUNT(*) AS InterfaceCount
FROM Orion.NPM.Interfaces
GROUP BY
  CASE
    WHEN Speed >= 10000000000 THEN '10 Gbps+'
    WHEN Speed >= 1000000000 THEN '1 Gbps'
    WHEN Speed >= 100000000 THEN '100 Mbps'
    WHEN Speed >= 10000000 THEN '10 Mbps'
    ELSE 'Unter 10 Mbps'
  END
ORDER BY InterfaceCount DESC`
      },
      {
        name: 'Top Traffic Interfaces',
        description: 'Interfaces mit höchstem Traffic',
        query: `SELECT TOP 20 i.Caption, i.InBps, i.OutBps, (i.InBps + i.OutBps) AS TotalBps, n.Caption AS NodeName
FROM Orion.NPM.Interfaces i
INNER JOIN Orion.Nodes n ON i.NodeID = n.NodeID
ORDER BY TotalBps DESC`
      }
    ]
  },
  volumes: {
    name: 'Volumes',
    icon: '💾',
    templates: [
      {
        name: 'Alle Volumes',
        description: 'Komplette Volume-Übersicht',
        query: `SELECT v.VolumeID, v.Caption, v.VolumePercentUsed, v.VolumeSize, v.VolumeSpaceAvailable, n.Caption AS NodeName
FROM Orion.Volumes v
INNER JOIN Orion.Nodes n ON v.NodeID = n.NodeID
ORDER BY n.Caption, v.Caption`
      },
      {
        name: 'Kritischer Speicherplatz',
        description: 'Volumes mit weniger als 10% freiem Speicher',
        query: `SELECT v.Caption, v.VolumePercentUsed, v.VolumeSpaceAvailable, v.VolumeSize, n.Caption AS NodeName
FROM Orion.Volumes v
INNER JOIN Orion.Nodes n ON v.NodeID = n.NodeID
WHERE v.VolumePercentUsed > 90
ORDER BY v.VolumePercentUsed DESC`
      },
      {
        name: 'Warnung Speicherplatz',
        description: 'Volumes zwischen 80-90% belegt',
        query: `SELECT v.Caption, v.VolumePercentUsed, v.VolumeSpaceAvailable, v.VolumeSize, n.Caption AS NodeName
FROM Orion.Volumes v
INNER JOIN Orion.Nodes n ON v.NodeID = n.NodeID
WHERE v.VolumePercentUsed BETWEEN 80 AND 90
ORDER BY v.VolumePercentUsed DESC`
      },
      {
        name: 'Volumes nach Status',
        description: 'Volume-Status Übersicht',
        query: `SELECT StatusDescription, COUNT(*) AS VolumeCount
FROM Orion.Volumes
GROUP BY StatusDescription
ORDER BY VolumeCount DESC`
      },
      {
        name: 'Größte Volumes',
        description: 'Top 20 größte Volumes',
        query: `SELECT TOP 20 v.Caption, v.VolumeSize, v.VolumePercentUsed, n.Caption AS NodeName
FROM Orion.Volumes v
INNER JOIN Orion.Nodes n ON v.NodeID = n.NodeID
ORDER BY v.VolumeSize DESC`
      },
      {
        name: 'Unmanaged Volumes',
        description: 'Volumes die nicht überwacht werden',
        query: `SELECT v.Caption, v.VolumeType, n.Caption AS NodeName
FROM Orion.Volumes v
INNER JOIN Orion.Nodes n ON v.NodeID = n.NodeID
WHERE v.Unmanaged = TRUE
ORDER BY n.Caption`
      },
      {
        name: 'Volume-Typen Übersicht',
        description: 'Statistik nach Volume-Typ',
        query: `SELECT VolumeType, COUNT(*) AS VolumeCount, AVG(VolumePercentUsed) AS AvgUsedPercent
FROM Orion.Volumes
GROUP BY VolumeType
ORDER BY VolumeCount DESC`
      }
    ]
  },
  performance: {
    name: 'Performance',
    icon: '📊',
    templates: [
      {
        name: 'Top CPU Consumer',
        description: 'Top 20 Nodes nach CPU-Last',
        query: `SELECT TOP 20 NodeID, Caption, IP_Address, CPULoad, Vendor
FROM Orion.Nodes
WHERE CPULoad IS NOT NULL
ORDER BY CPULoad DESC`
      },
      {
        name: 'Top Memory Consumer',
        description: 'Top 20 Nodes nach RAM-Nutzung',
        query: `SELECT TOP 20 NodeID, Caption, IP_Address, PercentMemoryUsed, TotalMemory, Vendor
FROM Orion.Nodes
WHERE PercentMemoryUsed IS NOT NULL
ORDER BY PercentMemoryUsed DESC`
      },
      {
        name: 'Response Time Ranking',
        description: 'Nodes nach Response Time sortiert',
        query: `SELECT TOP 30 NodeID, Caption, IP_Address, ResponseTime, AvgResponseTime, MaxResponseTime
FROM Orion.Nodes
WHERE ResponseTime IS NOT NULL
ORDER BY ResponseTime DESC`
      },
      {
        name: 'Multi-CPU Load Details',
        description: 'Detaillierte CPU-Last pro Kern',
        query: `SELECT c.NodeID, n.Caption, c.CPUIndex, c.AvgLoad, c.MinLoad, c.MaxLoad
FROM Orion.CPUMultiLoad c
INNER JOIN Orion.Nodes n ON c.NodeID = n.NodeID
WHERE c.AvgLoad > 50
ORDER BY c.AvgLoad DESC`
      },
      {
        name: 'Polling Engine Status',
        description: 'Status der Polling Engines',
        query: `SELECT EngineID, ServerName, IP, PollingCompletion, Elements, Nodes, Interfaces, Volumes
FROM Orion.Engines
ORDER BY EngineID`
      },
      {
        name: 'Nodes mit schlechter Erreichbarkeit',
        description: 'Nodes mit Packet Loss und hoher Latenz',
        query: `SELECT NodeID, Caption, IP_Address, PercentLoss, ResponseTime, StatusDescription
FROM Orion.Nodes
WHERE PercentLoss > 0 OR ResponseTime > 100
ORDER BY PercentLoss DESC, ResponseTime DESC`
      }
    ]
  },
  events: {
    name: 'Events & Audit',
    icon: '📝',
    templates: [
      {
        name: 'Letzte Events',
        description: 'Die letzten 100 Events',
        query: `SELECT TOP 100 EventID, EventTime, Message, EventType, NetworkNode
FROM Orion.Events
ORDER BY EventTime DESC`
      },
      {
        name: 'Events nach Typ',
        description: 'Event-Statistik nach Typ',
        query: `SELECT EventType, COUNT(*) AS EventCount
FROM Orion.Events
WHERE EventTime > ADDDAY(-7, GETUTCDATE())
GROUP BY EventType
ORDER BY EventCount DESC`
      },
      {
        name: 'Events für Node',
        description: 'Events für einen bestimmten Node (Parameter: @nodeId)',
        query: `SELECT TOP 50 EventID, EventTime, Message, EventType
FROM Orion.Events
WHERE NetworkNode = @nodeId
ORDER BY EventTime DESC`
      },
      {
        name: 'Audit Log (letzte 24h)',
        description: 'Audit-Einträge der letzten 24 Stunden',
        query: `SELECT AuditEventID, TimeLoggedUtc, AccountID, AuditEventMessage, NetObjectType
FROM Orion.Audit
WHERE TimeLoggedUtc > ADDDAY(-1, GETUTCDATE())
ORDER BY TimeLoggedUtc DESC`
      },
      {
        name: 'Audit nach Benutzer',
        description: 'Audit-Aktivitäten gruppiert nach User',
        query: `SELECT AccountID, COUNT(*) AS ActionCount
FROM Orion.Audit
WHERE TimeLoggedUtc > ADDDAY(-7, GETUTCDATE())
GROUP BY AccountID
ORDER BY ActionCount DESC`
      },
      {
        name: 'Config Changes (Audit)',
        description: 'Konfigurationsänderungen im Audit Log',
        query: `SELECT TimeLoggedUtc, AccountID, AuditEventMessage
FROM Orion.Audit
WHERE AuditEventMessage LIKE '%config%' OR AuditEventMessage LIKE '%setting%'
ORDER BY TimeLoggedUtc DESC`
      }
    ]
  },
  groups: {
    name: 'Gruppen',
    icon: '📁',
    templates: [
      {
        name: 'Alle Gruppen',
        description: 'Übersicht aller Orion-Gruppen',
        query: `SELECT ContainerID, Name, Description, Owner, Status, StatusDescription
FROM Orion.Groups
ORDER BY Name`
      },
      {
        name: 'Gruppen mit Problemen',
        description: 'Gruppen die nicht im Up-Status sind',
        query: `SELECT ContainerID, Name, Description, Status, StatusDescription
FROM Orion.Groups
WHERE Status != 1
ORDER BY Status DESC`
      },
      {
        name: 'Gruppen-Statistik',
        description: 'Anzahl Gruppen nach Status',
        query: `SELECT StatusDescription, COUNT(*) AS GroupCount
FROM Orion.Groups
GROUP BY StatusDescription
ORDER BY GroupCount DESC`
      }
    ]
  },
  virtualization: {
    name: 'Virtualisierung',
    icon: '☁️',
    templates: [
      {
        name: 'Alle VMs',
        description: 'Übersicht aller virtuellen Maschinen',
        query: `SELECT VirtualMachineID, Name, IPAddress, PowerState, GuestState, CPUCount, MemoryConfigured
FROM Orion.VIM.VirtualMachines
ORDER BY Name`
      },
      {
        name: 'Powered Off VMs',
        description: 'VMs die ausgeschaltet sind',
        query: `SELECT VirtualMachineID, Name, IPAddress, PowerState
FROM Orion.VIM.VirtualMachines
WHERE PowerState = 'poweredOff'
ORDER BY Name`
      },
      {
        name: 'VM CPU/Memory Usage',
        description: 'VMs nach Ressourcennutzung',
        query: `SELECT Name, CpuUsageMHz, MemUsage, CPUCount, MemoryConfigured
FROM Orion.VIM.VirtualMachines
WHERE PowerState = 'poweredOn'
ORDER BY MemUsage DESC`
      },
      {
        name: 'VMs ohne VMware Tools',
        description: 'VMs mit problematischen VMware Tools',
        query: `SELECT Name, IPAddress, VMwareToolsStatus, PowerState
FROM Orion.VIM.VirtualMachines
WHERE VMwareToolsStatus != 'guestToolsRunning' AND PowerState = 'poweredOn'
ORDER BY Name`
      }
    ]
  },
  ncm: {
    name: 'NCM',
    icon: '⚙️',
    templates: [
      {
        name: 'NCM Nodes Übersicht',
        description: 'Alle NCM-überwachten Nodes',
        query: `SELECT NodeID, NodeCaption, AgentIP, LastConfigDownload, LastInventory, ConfigStatus
FROM NCM.Nodes
ORDER BY NodeCaption`
      },
      {
        name: 'Letzte Config-Downloads',
        description: 'Kürzlich heruntergeladene Configs',
        query: `SELECT NodeCaption, LastConfigDownload, ConfigStatus
FROM NCM.Nodes
WHERE LastConfigDownload > ADDDAY(-7, GETUTCDATE())
ORDER BY LastConfigDownload DESC`
      },
      {
        name: 'Veraltete Configs',
        description: 'Nodes ohne kürzliche Config-Downloads',
        query: `SELECT NodeCaption, LastConfigDownload, ConfigStatus
FROM NCM.Nodes
WHERE LastConfigDownload < ADDDAY(-30, GETUTCDATE()) OR LastConfigDownload IS NULL
ORDER BY LastConfigDownload`
      }
    ]
  },
  ipam: {
    name: 'IPAM',
    icon: '🌐',
    templates: [
      {
        name: 'Alle Subnets',
        description: 'IPAM Subnet-Übersicht',
        query: `SELECT SubnetId, Address, CIDR, FriendlyName, VLAN, PercentUsed, UsedCount, AvailableCount
FROM IPAM.Subnet
ORDER BY Address`
      },
      {
        name: 'Volle Subnets',
        description: 'Subnets mit hoher Auslastung (>80%)',
        query: `SELECT Address, CIDR, FriendlyName, PercentUsed, UsedCount, AvailableCount
FROM IPAM.Subnet
WHERE PercentUsed > 80
ORDER BY PercentUsed DESC`
      },
      {
        name: 'Leere Subnets',
        description: 'Subnets ohne genutzte IPs',
        query: `SELECT Address, CIDR, FriendlyName, Description, Location
FROM IPAM.Subnet
WHERE UsedCount = 0
ORDER BY Address`
      },
      {
        name: 'IP-Adressen suchen',
        description: 'IP-Adresse finden (Parameter: @ip)',
        query: `SELECT IPAddress, SubnetId, StatusName, DnsBackward, MAC, SystemName, Comments
FROM IPAM.IPNode
WHERE IPAddress LIKE @ip`
      },
      {
        name: 'Benutzte IPs in Subnet',
        description: 'Alle genutzten IPs in einem Subnet (Parameter: @subnetId)',
        query: `SELECT IPAddress, StatusName, DnsBackward, MAC, SystemName, LastSync
FROM IPAM.IPNode
WHERE SubnetId = @subnetId AND Status = 2
ORDER BY IPAddress`
      }
    ]
  },
  admin: {
    name: 'Administration',
    icon: '🔧',
    templates: [
      {
        name: 'Benutzer-Übersicht',
        description: 'Alle Orion-Benutzer',
        query: `SELECT AccountID, Enabled, AllowAdmin, LastLogin, AccountType
FROM Orion.Accounts
ORDER BY AccountID`
      },
      {
        name: 'Admin-Accounts',
        description: 'Benutzer mit Admin-Rechten',
        query: `SELECT AccountID, Enabled, LastLogin
FROM Orion.Accounts
WHERE AllowAdmin = TRUE
ORDER BY AccountID`
      },
      {
        name: 'Inaktive Benutzer',
        description: 'Benutzer ohne Login in den letzten 30 Tagen',
        query: `SELECT AccountID, LastLogin, Enabled
FROM Orion.Accounts
WHERE LastLogin < ADDDAY(-30, GETUTCDATE()) OR LastLogin IS NULL
ORDER BY LastLogin`
      },
      {
        name: 'Polling-Status',
        description: 'Status aller aktiven Poller',
        query: `SELECT PollerID, PollerType, NetObjectType, Enabled
FROM Orion.Pollers
WHERE Enabled = TRUE
ORDER BY PollerType`
      },
      {
        name: 'Deaktivierte Poller',
        description: 'Alle deaktivierten Poller',
        query: `SELECT PollerID, PollerType, NetObjectType, NetObjectID
FROM Orion.Pollers
WHERE Enabled = FALSE
ORDER BY PollerType`
      },
      {
        name: 'Custom Properties Übersicht',
        description: 'Nodes mit Custom Properties',
        query: `SELECT n.NodeID, n.Caption, cp.City, cp.Department, cp.Comments
FROM Orion.Nodes n
LEFT JOIN Orion.NodesCustomProperties cp ON n.NodeID = cp.NodeID
WHERE cp.City IS NOT NULL OR cp.Department IS NOT NULL
ORDER BY n.Caption`
      }
    ]
  }
}

// ============================================
// SWQL CHEAT SHEET
// ============================================

const swqlCheatSheet = {
  basics: {
    title: 'Grundlagen',
    items: [
      { syntax: 'SELECT column1, column2 FROM Entity', description: 'Spalten auswählen' },
      { syntax: 'SELECT * FROM Entity', description: 'Alle Spalten (vermeiden!)' },
      { syntax: 'SELECT DISTINCT column FROM Entity', description: 'Nur eindeutige Werte' },
      { syntax: 'SELECT TOP 10 column FROM Entity', description: 'Erste N Ergebnisse' },
      { syntax: 'WHERE column = value', description: 'Filter-Bedingung' },
      { syntax: 'WHERE column LIKE \'%text%\'', description: 'Pattern-Matching' },
      { syntax: 'WHERE column IN (1, 2, 3)', description: 'Wert in Liste' },
      { syntax: 'WHERE column BETWEEN 1 AND 10', description: 'Wert im Bereich' },
      { syntax: 'WHERE column IS NULL', description: 'Null-Prüfung' },
      { syntax: 'ORDER BY column ASC/DESC', description: 'Sortierung' },
      { syntax: 'GROUP BY column', description: 'Gruppierung' },
      { syntax: 'HAVING COUNT(*) > 5', description: 'Gruppen-Filter' }
    ]
  },
  joins: {
    title: 'JOINs',
    items: [
      { syntax: 'INNER JOIN Entity2 ON Entity1.ID = Entity2.ID', description: 'Inner Join' },
      { syntax: 'LEFT JOIN Entity2 ON Entity1.ID = Entity2.ID', description: 'Left Outer Join' },
      { syntax: 'Entity1.NavigationProperty', description: 'Navigation Property (impliziter Join)' }
    ]
  },
  functions: {
    title: 'Funktionen',
    items: [
      { syntax: 'COUNT(*)', description: 'Anzahl Zeilen' },
      { syntax: 'SUM(column)', description: 'Summe' },
      { syntax: 'AVG(column)', description: 'Durchschnitt' },
      { syntax: 'MIN(column) / MAX(column)', description: 'Minimum / Maximum' },
      { syntax: 'CONCAT(str1, str2)', description: 'Strings verbinden' },
      { syntax: 'SUBSTRING(str, start, length)', description: 'Teil-String' },
      { syntax: 'LENGTH(str)', description: 'String-Länge' },
      { syntax: 'TOLOWER(str) / TOUPPER(str)', description: 'Groß-/Kleinschreibung' },
      { syntax: 'ISNULL(column, default)', description: 'Null ersetzen' }
    ]
  },
  datetime: {
    title: 'Datum & Zeit',
    items: [
      { syntax: 'GETDATE()', description: 'Aktuelles Datum/Zeit (lokal)' },
      { syntax: 'GETUTCDATE()', description: 'Aktuelles Datum/Zeit (UTC)' },
      { syntax: 'ADDDAY(n, date)', description: 'Tage addieren' },
      { syntax: 'ADDHOUR(n, date)', description: 'Stunden addieren' },
      { syntax: 'ADDMINUTE(n, date)', description: 'Minuten addieren' },
      { syntax: 'ADDMONTH(n, date)', description: 'Monate addieren' },
      { syntax: 'ADDYEAR(n, date)', description: 'Jahre addieren' },
      { syntax: 'DAYDIFF(date1, date2)', description: 'Tage Differenz' },
      { syntax: 'HOURDIFF(date1, date2)', description: 'Stunden Differenz' },
      { syntax: 'TOLOCAL(date)', description: 'Nach lokaler Zeit konvertieren' },
      { syntax: 'TOUTC(date)', description: 'Nach UTC konvertieren' },
      { syntax: 'YEAR(date) / MONTH(date) / DAY(date)', description: 'Teil extrahieren' },
      { syntax: 'HOUR(date) / MINUTE(date) / SECOND(date)', description: 'Zeit-Teil extrahieren' },
      { syntax: 'WEEKDAY(date)', description: 'Wochentag (1=Sonntag)' }
    ]
  },
  numeric: {
    title: 'Numerische Funktionen',
    items: [
      { syntax: 'ABS(number)', description: 'Absoluter Wert' },
      { syntax: 'ROUND(number, decimals)', description: 'Runden' },
      { syntax: 'FLOOR(number)', description: 'Abrunden' },
      { syntax: 'CEILING(number)', description: 'Aufrunden' }
    ]
  },
  case: {
    title: 'CASE Statements',
    items: [
      { syntax: 'CASE column WHEN value1 THEN result1 WHEN value2 THEN result2 ELSE default END', description: 'Simple CASE' },
      { syntax: 'CASE WHEN condition1 THEN result1 WHEN condition2 THEN result2 ELSE default END', description: 'Searched CASE' }
    ]
  },
  parameters: {
    title: 'Parameter',
    items: [
      { syntax: '@paramName', description: 'Parameter-Platzhalter in Query' },
      { syntax: 'Get-SwisData $swis "..." @{paramName="value"}', description: 'PowerShell Parameter-Übergabe' }
    ]
  },
  statusCodes: {
    title: 'Status-Codes',
    items: [
      { syntax: 'Status = 1', description: 'Up / OK' },
      { syntax: 'Status = 2', description: 'Down / Critical' },
      { syntax: 'Status = 3', description: 'Warning' },
      { syntax: 'Status = 4', description: 'Shutdown' },
      { syntax: 'Status = 9', description: 'Unmanaged' },
      { syntax: 'Status = 12', description: 'Unreachable' },
      { syntax: 'Status = 14', description: 'Critical' },
      { syntax: 'Status = 15', description: 'Mixed availability (Gruppe)' },
      { syntax: 'Status = 16', description: 'Misconfigured' },
      { syntax: 'Status = 17', description: 'Could not poll' }
    ]
  }
}

// ============================================
// VERB DEFINITIONS
// ============================================

const verbDefinitions = {
  'Unmanage': {
    entity: 'Orion.Nodes',
    description: 'Node(s) für einen Zeitraum aus dem Monitoring nehmen',
    params: [
      { name: 'netObjectId', type: 'string', description: 'N:NodeID (z.B. N:123)' },
      { name: 'start', type: 'datetime', description: 'Start-Zeitpunkt' },
      { name: 'end', type: 'datetime', description: 'End-Zeitpunkt' },
      { name: 'isRelative', type: 'bool', description: 'Relative Zeit?' }
    ],
    example: `$nodeId = 123
$now = [DateTime]::UtcNow
$later = $now.AddHours(2)
Invoke-SwisVerb $swis Orion.Nodes Unmanage @("N:$nodeId", $now, $later, $false)`
  },
  'Remanage': {
    entity: 'Orion.Nodes',
    description: 'Node(s) wieder ins Monitoring aufnehmen',
    params: [
      { name: 'netObjectId', type: 'string', description: 'N:NodeID (z.B. N:123)' }
    ],
    example: `$nodeId = 123
Invoke-SwisVerb $swis Orion.Nodes Remanage @("N:$nodeId")`
  },
  'PollNow': {
    entity: 'Orion.Nodes',
    description: 'Sofortiges Polling eines Nodes auslösen',
    params: [
      { name: 'netObjectId', type: 'string', description: 'N:NodeID' }
    ],
    example: `$nodeId = 123
Invoke-SwisVerb $swis Orion.Nodes PollNow @("N:$nodeId")`
  },
  'Acknowledge': {
    entity: 'Orion.AlertActive',
    description: 'Alert(s) bestätigen',
    params: [
      { name: 'alertObjectIds', type: 'array', description: 'Array von AlertObjectIDs' },
      { name: 'note', type: 'string', description: 'Bestätigungs-Notiz' }
    ],
    example: `$alertIds = @(1, 2, 3)
$note = "Acknowledged via API"
Invoke-SwisVerb $swis Orion.AlertActive Acknowledge @($alertIds, $note)`
  },
  'ClearAlert': {
    entity: 'Orion.AlertActive',
    description: 'Alert manuell zurücksetzen',
    params: [
      { name: 'alertObjectIds', type: 'array', description: 'Array von AlertObjectIDs' }
    ],
    example: `$alertIds = @(1, 2, 3)
Invoke-SwisVerb $swis Orion.AlertActive ClearAlert @($alertIds)`
  },
  'AppendNote': {
    entity: 'Orion.AlertActive',
    description: 'Notiz zu Alert hinzufügen (ohne Acknowledge)',
    params: [
      { name: 'alertObjectId', type: 'int', description: 'AlertObjectID' },
      { name: 'note', type: 'string', description: 'Notiz-Text' }
    ],
    example: `$alertId = 123
$note = "Investigation in progress"
Invoke-SwisVerb $swis Orion.AlertActive AppendNote @($alertId, $note)`
  },
  'SuppressAlerts': {
    entity: 'Orion.AlertSuppression',
    description: 'Alerts für Entity unterdrücken (Maintenance)',
    params: [
      { name: 'entityUri', type: 'string', description: 'Entity URI' },
      { name: 'suppressUntil', type: 'datetime', description: 'Unterdrücken bis' }
    ],
    example: `$uri = "swis://localhost/Orion/Orion.Nodes/NodeID=123"
$until = [DateTime]::UtcNow.AddHours(4)
Invoke-SwisVerb $swis Orion.AlertSuppression SuppressAlerts @($uri, $until)`
  },
  'SetCustomProperty': {
    entity: 'Orion.NodesCustomProperties',
    description: 'Custom Property eines Nodes setzen',
    params: [
      { name: 'uri', type: 'string', description: 'Node URI' },
      { name: 'propertyName', type: 'string', description: 'Property-Name' },
      { name: 'propertyValue', type: 'string', description: 'Property-Wert' }
    ],
    example: `$nodeUri = Get-SwisData $swis "SELECT Uri FROM Orion.Nodes WHERE NodeID = 123"
Set-SwisObject $swis $nodeUri @{CustomProperties.City = "Berlin"}`
  }
}

// ============================================
// COMPUTED PROPERTIES
// ============================================

const currentEntityProperties = computed(() => {
  return swqlSchema[selectedEntity.value]?.properties || []
})

const filteredSchema = computed(() => {
  if (!searchSchema.value.trim()) return swqlSchema
  const query = searchSchema.value.toLowerCase()
  const filtered = {}
  for (const [entity, data] of Object.entries(swqlSchema)) {
    if (entity.toLowerCase().includes(query) ||
        data.description.toLowerCase().includes(query) ||
        data.properties.some(p => p.name.toLowerCase().includes(query))) {
      filtered[entity] = data
    }
  }
  return filtered
})

const filteredTemplates = computed(() => {
  if (!searchTemplates.value.trim()) return queryTemplates
  const query = searchTemplates.value.toLowerCase()
  const filtered = {}
  for (const [key, category] of Object.entries(queryTemplates)) {
    const matchingTemplates = category.templates.filter(t =>
      t.name.toLowerCase().includes(query) ||
      t.description.toLowerCase().includes(query) ||
      t.query.toLowerCase().includes(query)
    )
    if (matchingTemplates.length > 0) {
      filtered[key] = { ...category, templates: matchingTemplates }
    }
  }
  return filtered
})

const generatedQuery = computed(() => {
  if (!selectedEntity.value || selectedColumns.value.length === 0) {
    return ''
  }

  let query = 'SELECT '

  if (distinctResults.value) {
    query += 'DISTINCT '
  }

  if (topCount.value && parseInt(topCount.value) > 0) {
    query += `TOP ${topCount.value} `
  }

  query += selectedColumns.value.join(', ')
  query += `\nFROM ${selectedEntity.value}`

  if (whereConditions.value.length > 0) {
    const conditions = whereConditions.value
      .filter(c => c.column && c.operator && c.value)
      .map(c => {
        let value = c.value
        if (c.operator === 'LIKE') {
          value = `'%${value}%'`
        } else if (c.operator === 'IN') {
          value = `(${value})`
        } else if (isNaN(value) && value !== 'TRUE' && value !== 'FALSE' && value !== 'NULL') {
          value = `'${value}'`
        }
        return `${c.column} ${c.operator} ${value}`
      })

    if (conditions.length > 0) {
      query += `\nWHERE ${conditions.join('\n  AND ')}`
    }
  }

  if (orderBy.value) {
    query += `\nORDER BY ${orderBy.value} ${orderDirection.value}`
  }

  return query
})

const powershellScript = computed(() => {
  const conn = connections.value.find(c => c.id === selectedConnection.value) || connections.value[0]
  if (!conn || !generatedQuery.value) return ''

  return `# SolarWinds Orion - SWQL Query
# Generiert am: ${new Date().toLocaleString('de-DE')}

# Verbindung herstellen
$hostname = "${conn.hostname}"
$swis = Connect-Swis -Hostname $hostname -Credential (Get-Credential)

# SWQL Query
$query = @"
${generatedQuery.value}
"@

# Query ausführen
$result = Get-SwisData $swis $query

# Ergebnis anzeigen
$result | Format-Table -AutoSize

# Optional: Export als CSV
# $result | Export-Csv -Path "export.csv" -NoTypeInformation -Encoding UTF8

# Optional: Export als JSON
# $result | ConvertTo-Json -Depth 10 | Out-File "export.json" -Encoding UTF8`
})

const verbPowershellScript = computed(() => {
  const verb = verbDefinitions[selectedVerb.value]
  if (!verb) return ''

  return verb.example
})

// ============================================
// METHODS
// ============================================

function addWhereCondition() {
  whereConditions.value.push({
    column: '',
    operator: '=',
    value: ''
  })
}

function removeWhereCondition(index) {
  whereConditions.value.splice(index, 1)
}

function toggleColumn(column) {
  const index = selectedColumns.value.indexOf(column)
  if (index === -1) {
    selectedColumns.value.push(column)
  } else {
    selectedColumns.value.splice(index, 1)
  }
}

function selectAllColumns() {
  selectedColumns.value = currentEntityProperties.value.map(p => p.name)
}

function clearColumns() {
  selectedColumns.value = []
}

function loadTemplate(template) {
  // Parse and load template into builder or just show it
  activeTab.value = 'builder'
  // For now, we'll just copy the query
  navigator.clipboard.writeText(template.query)
  showCopied()
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
  showCopied()
}

function showCopied() {
  copied.value = true
  setTimeout(() => copied.value = false, 2000)
}

function toggleEntity(entity) {
  const index = expandedEntities.value.indexOf(entity)
  if (index === -1) {
    expandedEntities.value.push(entity)
  } else {
    expandedEntities.value.splice(index, 1)
  }
}

function toggleCategory(category) {
  const index = expandedCategories.value.indexOf(category)
  if (index === -1) {
    expandedCategories.value.push(category)
  } else {
    expandedCategories.value.splice(index, 1)
  }
}

function changeEntity(entity) {
  selectedEntity.value = entity
  selectedColumns.value = swqlSchema[entity]?.properties.slice(0, 5).map(p => p.name) || []
  whereConditions.value = []
  orderBy.value = ''
}

function addConnection() {
  if (newConnection.value.name && newConnection.value.hostname) {
    connections.value.push({
      id: Date.now(),
      ...newConnection.value,
      isDefault: false
    })
    newConnection.value = { name: '', hostname: '', username: '', password: '' }
    showAddConnection.value = false
  }
}

function removeConnection(id) {
  const index = connections.value.findIndex(c => c.id === id)
  if (index !== -1 && connections.value.length > 1) {
    connections.value.splice(index, 1)
    if (selectedConnection.value === id) {
      selectedConnection.value = connections.value[0].id
    }
  }
}

function setDefaultConnection(id) {
  connections.value.forEach(c => c.isDefault = c.id === id)
  selectedConnection.value = id
}

function formatQuery(query) {
  // Basic SWQL formatting
  let formatted = query
    .replace(/\s+/g, ' ')
    .replace(/SELECT /gi, 'SELECT\n  ')
    .replace(/, /g, ',\n  ')
    .replace(/ FROM /gi, '\nFROM ')
    .replace(/ INNER JOIN /gi, '\nINNER JOIN ')
    .replace(/ LEFT JOIN /gi, '\nLEFT JOIN ')
    .replace(/ WHERE /gi, '\nWHERE ')
    .replace(/ AND /gi, '\n  AND ')
    .replace(/ OR /gi, '\n  OR ')
    .replace(/ ORDER BY /gi, '\nORDER BY ')
    .replace(/ GROUP BY /gi, '\nGROUP BY ')
    .replace(/ HAVING /gi, '\nHAVING ')
  return formatted.trim()
}

// ============================================
// TABS CONFIGURATION
// ============================================

const tabs = [
  { id: 'builder', name: 'Query Builder', icon: TableCellsIcon },
  { id: 'templates', name: 'Templates', icon: BookmarkIcon },
  { id: 'schema', name: 'Schema', icon: DocumentTextIcon },
  { id: 'powershell', name: 'PowerShell', icon: CommandLineIcon },
  { id: 'verbs', name: 'Verb Builder', icon: PlayIcon },
  { id: 'connections', name: 'Connections', icon: ServerIcon },
  { id: 'cheatsheet', name: 'Cheat Sheet', icon: InformationCircleIcon }
]
</script>

<template>
  <div class="h-full flex flex-col -m-4">
    <!-- Tabs -->
    <div class="flex border-b border-white/[0.06] bg-white/[0.04] px-2 overflow-x-auto">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="activeTab = tab.id"
        class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
        :class="activeTab === tab.id
          ? 'text-primary-400 border-primary-400'
          : 'text-gray-400 border-transparent hover:text-gray-200'"
      >
        <component :is="tab.icon" class="w-4 h-4" />
        {{ tab.name }}
      </button>
    </div>

    <!-- Tab Content -->
    <div class="flex-1 overflow-auto p-4">

      <!-- Query Builder Tab -->
      <div v-if="activeTab === 'builder'" class="space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <!-- Left: Configuration -->
          <div class="space-y-4">
            <!-- Entity Selection -->
            <div class="card p-4">
              <h3 class="text-sm font-semibold text-white mb-3">1. Entity auswählen</h3>
              <select v-model="selectedEntity" @change="changeEntity(selectedEntity)" class="input w-full">
                <optgroup v-for="(group, idx) in [
                  { name: 'Nodes & Monitoring', entities: ['Orion.Nodes', 'Orion.NPM.Interfaces', 'Orion.Volumes', 'Orion.CPUMultiLoad'] },
                  { name: 'Alerts', entities: ['Orion.AlertActive', 'Orion.AlertConfigurations', 'Orion.AlertHistory', 'Orion.AlertObjects', 'Orion.AlertSuppression'] },
                  { name: 'System', entities: ['Orion.Events', 'Orion.Pollers', 'Orion.Groups', 'Orion.Engines', 'Orion.Accounts', 'Orion.Audit'] },
                  { name: 'Custom Properties', entities: ['Orion.NodesCustomProperties'] },
                  { name: 'Virtualisierung', entities: ['Orion.VIM.VirtualMachines'] },
                  { name: 'NCM & IPAM', entities: ['NCM.Nodes', 'IPAM.IPNode', 'IPAM.Subnet'] }
                ]" :key="idx" :label="group.name">
                  <option v-for="entity in group.entities" :key="entity" :value="entity">{{ entity }}</option>
                </optgroup>
              </select>
              <p class="text-xs text-gray-500 mt-2">{{ swqlSchema[selectedEntity]?.description }}</p>
            </div>

            <!-- Column Selection -->
            <div class="card p-4">
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-white">2. Spalten auswählen</h3>
                <div class="flex gap-2">
                  <button @click="selectAllColumns" class="text-xs text-primary-400 hover:text-primary-300">Alle</button>
                  <button @click="clearColumns" class="text-xs text-gray-400 hover:text-gray-300">Keine</button>
                </div>
              </div>
              <div class="max-h-48 overflow-auto space-y-1">
                <label
                  v-for="prop in currentEntityProperties"
                  :key="prop.name"
                  class="flex items-center gap-2 text-sm cursor-pointer hover:bg-white/[0.04] p-1 rounded"
                >
                  <input
                    type="checkbox"
                    :checked="selectedColumns.includes(prop.name)"
                    @change="toggleColumn(prop.name)"
                    class="rounded bg-white/[0.08] text-primary-500"
                  />
                  <span class="text-gray-300">{{ prop.name }}</span>
                  <span class="text-xs text-gray-500">({{ prop.type }})</span>
                </label>
              </div>
            </div>

            <!-- WHERE Conditions -->
            <div class="card p-4">
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-white">3. Filter (WHERE)</h3>
                <button @click="addWhereCondition" class="btn-icon text-primary-400">
                  <PlusIcon class="w-4 h-4" />
                </button>
              </div>
              <div class="space-y-2">
                <div v-for="(condition, index) in whereConditions" :key="index" class="flex gap-2 items-center">
                  <select v-model="condition.column" class="input flex-1 text-sm">
                    <option value="">Spalte...</option>
                    <option v-for="prop in currentEntityProperties" :key="prop.name" :value="prop.name">
                      {{ prop.name }}
                    </option>
                  </select>
                  <select v-model="condition.operator" class="input w-24 text-sm">
                    <option>=</option>
                    <option>!=</option>
                    <option>&lt;</option>
                    <option>&gt;</option>
                    <option>&lt;=</option>
                    <option>&gt;=</option>
                    <option>LIKE</option>
                    <option>IN</option>
                    <option>IS NULL</option>
                    <option>IS NOT NULL</option>
                  </select>
                  <input
                    v-model="condition.value"
                    class="input flex-1 text-sm"
                    placeholder="Wert"
                    :disabled="condition.operator === 'IS NULL' || condition.operator === 'IS NOT NULL'"
                  />
                  <button @click="removeWhereCondition(index)" class="btn-icon text-red-400">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
                <p v-if="whereConditions.length === 0" class="text-xs text-gray-500">Keine Filter definiert</p>
              </div>
            </div>

            <!-- Order & Options -->
            <div class="card p-4">
              <h3 class="text-sm font-semibold text-white mb-3">4. Sortierung & Optionen</h3>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-xs text-gray-400">ORDER BY</label>
                  <select v-model="orderBy" class="input w-full text-sm mt-1">
                    <option value="">Keine Sortierung</option>
                    <option v-for="col in selectedColumns" :key="col" :value="col">{{ col }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs text-gray-400">Richtung</label>
                  <select v-model="orderDirection" class="input w-full text-sm mt-1">
                    <option>ASC</option>
                    <option>DESC</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs text-gray-400">TOP (Limit)</label>
                  <input v-model="topCount" type="number" class="input w-full text-sm mt-1" placeholder="z.B. 100" />
                </div>
                <div class="flex items-end">
                  <label class="flex items-center gap-2 text-sm text-gray-300">
                    <input type="checkbox" v-model="distinctResults" class="rounded bg-white/[0.08]" />
                    DISTINCT
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Right: Generated Query -->
          <div class="space-y-4">
            <div class="card p-4 h-full flex flex-col">
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-white">Generierte SWQL Query</h3>
                <button
                  @click="copyToClipboard(generatedQuery)"
                  class="flex items-center gap-1 text-xs text-primary-400 hover:text-primary-300"
                >
                  <ClipboardIcon class="w-4 h-4" />
                  {{ copied ? 'Kopiert!' : 'Kopieren' }}
                </button>
              </div>
              <pre class="flex-1 p-3 bg-white/[0.02] rounded-lg text-sm font-mono overflow-auto text-green-400 whitespace-pre-wrap">{{ generatedQuery || '-- Wähle Entity und Spalten aus' }}</pre>
            </div>
          </div>
        </div>
      </div>

      <!-- Templates Tab -->
      <div v-if="activeTab === 'templates'" class="space-y-4">
        <!-- Search -->
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="searchTemplates"
            type="text"
            placeholder="Templates durchsuchen..."
            class="input w-full pl-10"
          />
        </div>

        <!-- Template Categories -->
        <div class="space-y-4">
          <div v-for="(category, key) in filteredTemplates" :key="key" class="card">
            <button
              @click="toggleCategory(key)"
              class="w-full flex items-center justify-between p-4 text-left"
            >
              <div class="flex items-center gap-3">
                <span class="text-xl">{{ category.icon }}</span>
                <span class="font-semibold text-white">{{ category.name }}</span>
                <span class="text-xs text-gray-500">({{ category.templates.length }})</span>
              </div>
              <ChevronDownIcon
                class="w-5 h-5 text-gray-400 transition-transform"
                :class="expandedCategories.includes(key) ? 'rotate-180' : ''"
              />
            </button>

            <div v-if="expandedCategories.includes(key)" class="border-t border-white/[0.06]">
              <div
                v-for="template in category.templates"
                :key="template.name"
                class="p-4 border-b border-white/[0.06] last:border-b-0 hover:bg-white/[0.04]"
              >
                <div class="flex items-start justify-between mb-2">
                  <div>
                    <h4 class="font-medium text-white">{{ template.name }}</h4>
                    <p class="text-xs text-gray-400">{{ template.description }}</p>
                  </div>
                  <button
                    @click="copyToClipboard(template.query)"
                    class="flex items-center gap-1 text-xs text-primary-400 hover:text-primary-300"
                  >
                    <ClipboardIcon class="w-4 h-4" />
                    Kopieren
                  </button>
                </div>
                <pre class="p-2 bg-white/[0.02] rounded text-xs font-mono overflow-auto text-green-400 max-h-32">{{ template.query }}</pre>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Schema Tab -->
      <div v-if="activeTab === 'schema'" class="space-y-4">
        <!-- Search -->
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="searchSchema"
            type="text"
            placeholder="Entity oder Property suchen..."
            class="input w-full pl-10"
          />
        </div>

        <!-- Schema Entities -->
        <div class="space-y-2">
          <div v-for="(data, entity) in filteredSchema" :key="entity" class="card">
            <button
              @click="toggleEntity(entity)"
              class="w-full flex items-center justify-between p-3 text-left"
            >
              <div>
                <span class="font-mono text-primary-400">{{ entity }}</span>
                <p class="text-xs text-gray-500 mt-1">{{ data.description }}</p>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500">{{ data.properties.length }} Props</span>
                <ChevronRightIcon
                  class="w-4 h-4 text-gray-400 transition-transform"
                  :class="expandedEntities.includes(entity) ? 'rotate-90' : ''"
                />
              </div>
            </button>

            <div v-if="expandedEntities.includes(entity)" class="border-t border-white/[0.06] p-3">
              <!-- Verbs -->
              <div v-if="data.verbs && data.verbs.length > 0" class="mb-3">
                <h4 class="text-xs font-semibold text-gray-400 mb-2">Verfügbare Verbs:</h4>
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="verb in data.verbs"
                    :key="verb"
                    class="px-2 py-0.5 bg-purple-900/30 text-purple-400 rounded text-xs"
                  >
                    {{ verb }}
                  </span>
                </div>
              </div>

              <!-- Properties Table -->
              <table class="w-full text-sm">
                <thead>
                  <tr class="text-left text-xs text-gray-500">
                    <th class="pb-2">Property</th>
                    <th class="pb-2">Typ</th>
                    <th class="pb-2">Beschreibung</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="prop in data.properties" :key="prop.name" class="border-t border-white/[0.06]">
                    <td class="py-2 font-mono text-green-400">{{ prop.name }}</td>
                    <td class="py-2 text-yellow-400">{{ prop.type }}</td>
                    <td class="py-2 text-gray-400">{{ prop.description }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- PowerShell Tab -->
      <div v-if="activeTab === 'powershell'" class="space-y-4">
        <div class="card p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-white">PowerShell Script</h3>
            <button
              @click="copyToClipboard(powershellScript)"
              class="flex items-center gap-1 text-xs text-primary-400 hover:text-primary-300"
            >
              <ClipboardIcon class="w-4 h-4" />
              {{ copied ? 'Kopiert!' : 'Kopieren' }}
            </button>
          </div>
          <p class="text-xs text-gray-400 mb-3">
            Basierend auf der Query aus dem Query Builder. Wechsle zum Builder-Tab um die Query anzupassen.
          </p>
          <pre class="p-4 bg-white/[0.02] rounded-lg text-sm font-mono overflow-auto text-blue-400 max-h-96">{{ powershellScript }}</pre>
        </div>

        <!-- Quick PowerShell Snippets -->
        <div class="card p-4">
          <h3 class="text-sm font-semibold text-white mb-3">Quick Snippets</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="p-3 bg-white/[0.04] rounded-lg">
              <h4 class="text-xs font-semibold text-gray-300 mb-2">Modul installieren</h4>
              <pre class="text-xs font-mono text-green-400">Install-Module SwisPowerShell</pre>
            </div>
            <div class="p-3 bg-white/[0.04] rounded-lg">
              <h4 class="text-xs font-semibold text-gray-300 mb-2">Modul laden</h4>
              <pre class="text-xs font-mono text-green-400">Import-Module SwisPowerShell</pre>
            </div>
            <div class="p-3 bg-white/[0.04] rounded-lg">
              <h4 class="text-xs font-semibold text-gray-300 mb-2">Verbindung mit Credentials</h4>
              <pre class="text-xs font-mono text-green-400">$swis = Connect-Swis -Hostname "orion" -Credential (Get-Credential)</pre>
            </div>
            <div class="p-3 bg-white/[0.04] rounded-lg">
              <h4 class="text-xs font-semibold text-gray-300 mb-2">Verbindung mit Windows Auth</h4>
              <pre class="text-xs font-mono text-green-400">$swis = Connect-Swis -Hostname "orion" -Trusted</pre>
            </div>
            <div class="p-3 bg-white/[0.04] rounded-lg">
              <h4 class="text-xs font-semibold text-gray-300 mb-2">Query mit Parameter</h4>
              <pre class="text-xs font-mono text-green-400">Get-SwisData $swis "SELECT * FROM Orion.Nodes WHERE Caption LIKE @name" @{name='%server%'}</pre>
            </div>
            <div class="p-3 bg-white/[0.04] rounded-lg">
              <h4 class="text-xs font-semibold text-gray-300 mb-2">Property aktualisieren</h4>
              <pre class="text-xs font-mono text-green-400">Set-SwisObject $swis $uri @{PropertyName='Value'}</pre>
            </div>
          </div>
        </div>
      </div>

      <!-- Verb Builder Tab -->
      <div v-if="activeTab === 'verbs'" class="space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <!-- Verb Selection -->
          <div class="space-y-4">
            <div class="card p-4">
              <h3 class="text-sm font-semibold text-white mb-3">Verb auswählen</h3>
              <select v-model="selectedVerb" class="input w-full">
                <option v-for="(verb, name) in verbDefinitions" :key="name" :value="name">
                  {{ name }} - {{ verb.description }}
                </option>
              </select>
            </div>

            <div class="card p-4" v-if="verbDefinitions[selectedVerb]">
              <h3 class="text-sm font-semibold text-white mb-3">Verb Details</h3>
              <div class="space-y-3">
                <div>
                  <span class="text-xs text-gray-400">Entity:</span>
                  <span class="ml-2 font-mono text-primary-400">{{ verbDefinitions[selectedVerb].entity }}</span>
                </div>
                <div>
                  <span class="text-xs text-gray-400">Beschreibung:</span>
                  <p class="text-sm text-gray-300 mt-1">{{ verbDefinitions[selectedVerb].description }}</p>
                </div>
                <div>
                  <span class="text-xs text-gray-400">Parameter:</span>
                  <div class="mt-2 space-y-2">
                    <div
                      v-for="param in verbDefinitions[selectedVerb].params"
                      :key="param.name"
                      class="flex items-center gap-2 text-sm"
                    >
                      <span class="font-mono text-yellow-400">{{ param.name }}</span>
                      <span class="text-gray-500">({{ param.type }})</span>
                      <span class="text-gray-400">- {{ param.description }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Generated Verb Script -->
          <div class="card p-4">
            <div class="flex items-center justify-between mb-3">
              <h3 class="text-sm font-semibold text-white">PowerShell Beispiel</h3>
              <button
                @click="copyToClipboard(verbPowershellScript)"
                class="flex items-center gap-1 text-xs text-primary-400 hover:text-primary-300"
              >
                <ClipboardIcon class="w-4 h-4" />
                {{ copied ? 'Kopiert!' : 'Kopieren' }}
              </button>
            </div>
            <pre class="p-4 bg-white/[0.02] rounded-lg text-sm font-mono overflow-auto text-blue-400">{{ verbPowershellScript }}</pre>
          </div>
        </div>

        <!-- Common Verb Examples -->
        <div class="card p-4">
          <h3 class="text-sm font-semibold text-white mb-3">Häufige Anwendungsfälle</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-3 bg-white/[0.04] rounded-lg">
              <h4 class="font-medium text-white mb-2">Mehrere Nodes unmanagen</h4>
              <pre class="text-xs font-mono text-green-400 whitespace-pre-wrap">$nodeIds = @(1, 2, 3)
$now = [DateTime]::UtcNow
$until = $now.AddHours(4)

foreach ($id in $nodeIds) {
    Invoke-SwisVerb $swis Orion.Nodes Unmanage @(
        "N:$id", $now, $until, $false
    )
}</pre>
            </div>
            <div class="p-3 bg-white/[0.04] rounded-lg">
              <h4 class="font-medium text-white mb-2">Alle Alerts acknowledgen</h4>
              <pre class="text-xs font-mono text-green-400 whitespace-pre-wrap">$alerts = Get-SwisData $swis @"
SELECT AlertObjectID
FROM Orion.AlertActive
WHERE Acknowledged = FALSE
"@

$ids = $alerts.AlertObjectID
$note = "Bulk acknowledged"

Invoke-SwisVerb $swis Orion.AlertActive Acknowledge @($ids, $note)</pre>
            </div>
            <div class="p-3 bg-white/[0.04] rounded-lg">
              <h4 class="font-medium text-white mb-2">Custom Property für viele Nodes setzen</h4>
              <pre class="text-xs font-mono text-green-400 whitespace-pre-wrap">$nodes = Get-SwisData $swis @"
SELECT Uri
FROM Orion.Nodes
WHERE Vendor = 'Cisco'
"@

foreach ($node in $nodes) {
    Set-SwisObject $swis $node.Uri @{
        'CustomProperties.Department' = 'Network'
    }
}</pre>
            </div>
            <div class="p-3 bg-white/[0.04] rounded-lg">
              <h4 class="font-medium text-white mb-2">Node remanagen nach Maintenance</h4>
              <pre class="text-xs font-mono text-green-400 whitespace-pre-wrap">$unmanaged = Get-SwisData $swis @"
SELECT NodeID
FROM Orion.Nodes
WHERE Unmanaged = TRUE
  AND UnManageUntil < GETUTCDATE()
"@

foreach ($node in $unmanaged) {
    Invoke-SwisVerb $swis Orion.Nodes Remanage @(
        "N:$($node.NodeID)"
    )
}</pre>
            </div>
          </div>
        </div>
      </div>

      <!-- Connections Tab -->
      <div v-if="activeTab === 'connections'" class="space-y-4">
        <div class="card p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Gespeicherte Verbindungen</h3>
            <button
              @click="showAddConnection = !showAddConnection"
              class="btn btn-primary text-sm"
            >
              <PlusIcon class="w-4 h-4 mr-1" />
              Neue Verbindung
            </button>
          </div>

          <!-- Add Connection Form -->
          <div v-if="showAddConnection" class="mb-4 p-4 bg-white/[0.04] rounded-lg">
            <h4 class="text-sm font-medium text-white mb-3">Neue Verbindung hinzufügen</h4>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs text-gray-400">Name</label>
                <input v-model="newConnection.name" class="input w-full mt-1" placeholder="z.B. Production" />
              </div>
              <div>
                <label class="text-xs text-gray-400">Hostname</label>
                <input v-model="newConnection.hostname" class="input w-full mt-1" placeholder="orion.company.com" />
              </div>
              <div>
                <label class="text-xs text-gray-400">Username (optional)</label>
                <input v-model="newConnection.username" class="input w-full mt-1" placeholder="admin" />
              </div>
              <div class="flex items-end">
                <button @click="addConnection" class="btn btn-primary w-full">Speichern</button>
              </div>
            </div>
          </div>

          <!-- Connection List -->
          <div class="space-y-2">
            <div
              v-for="conn in connections"
              :key="conn.id"
              class="flex items-center justify-between p-3 bg-white/[0.04] rounded-lg"
              :class="{ 'ring-1 ring-primary-500': conn.id === selectedConnection }"
            >
              <div class="flex items-center gap-3">
                <ServerIcon class="w-5 h-5 text-gray-400" />
                <div>
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-white">{{ conn.name }}</span>
                    <span v-if="conn.isDefault" class="text-xs bg-primary-900/50 text-primary-400 px-2 py-0.5 rounded">Default</span>
                  </div>
                  <span class="text-sm text-gray-400">{{ conn.hostname }}</span>
                  <span v-if="conn.username" class="text-sm text-gray-500 ml-2">({{ conn.username }})</span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <button
                  @click="setDefaultConnection(conn.id)"
                  class="btn-icon text-gray-400 hover:text-primary-400"
                  title="Als Standard setzen"
                >
                  <CheckIcon class="w-4 h-4" />
                </button>
                <button
                  @click="removeConnection(conn.id)"
                  class="btn-icon text-gray-400 hover:text-red-400"
                  :disabled="connections.length <= 1"
                  title="Entfernen"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="card p-4">
          <h3 class="text-sm font-semibold text-white mb-3">Verbindungs-Hinweise</h3>
          <div class="text-sm text-gray-400 space-y-2">
            <p>Die Verbindungen werden nur lokal im Browser gespeichert (LocalStorage). Passwörter werden NICHT gespeichert.</p>
            <p>Bei der Ausführung von PowerShell-Scripts wird <code class="text-primary-400">Get-Credential</code> verwendet, um sicher nach dem Passwort zu fragen.</p>
            <p>Für automatisierte Scripts empfehlen wir die Verwendung von Windows Integrated Authentication (<code class="text-primary-400">-Trusted</code>).</p>
          </div>
        </div>
      </div>

      <!-- Cheat Sheet Tab -->
      <div v-if="activeTab === 'cheatsheet'" class="space-y-4">
        <div v-for="(section, key) in swqlCheatSheet" :key="key" class="card p-4">
          <h3 class="text-sm font-semibold text-white mb-3">{{ section.title }}</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-xs text-gray-500 border-b border-white/[0.06]">
                  <th class="pb-2 pr-4">Syntax</th>
                  <th class="pb-2">Beschreibung</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in section.items" :key="item.syntax" class="border-b border-white/[0.06] last:border-0">
                  <td class="py-2 pr-4">
                    <code class="text-green-400 font-mono text-xs bg-white/[0.02] px-2 py-1 rounded">{{ item.syntax }}</code>
                  </td>
                  <td class="py-2 text-gray-400">{{ item.description }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

    <!-- Copied Toast -->
    <Transition name="fade">
      <div
        v-if="copied"
        class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-float flex items-center gap-2"
      >
        <CheckIcon class="w-5 h-5" />
        In Zwischenablage kopiert!
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
