# KyuubiSoft Feature Roadmap

Dieses Dokument beschreibt geplante Features und Erweiterungen für KyuubiSoft.

---

## 1. Web Push Notifications (Erweiterung)

### Status: Geplant
### Priorität: Hoch

### Beschreibung
Erweiterung des bestehenden Notification-Systems um Browser Push Notifications.

### Bestehende Infrastruktur
- In-App Notifications ✅
- Email Notifications ✅
- Webhook Notifications ✅
- Slack Integration ✅
- Telegram Integration ✅

### Neue Features

#### 1.1 Web Push API Integration
```
notification_channels:
  - channel_type: 'web_push' (NEU)
  - config: { endpoint, keys: { p256dh, auth } }
```

#### 1.2 Service Worker
- Push Event Listener
- Notification Display
- Click Handler (Navigation zur App)
- Badge Updates

#### 1.3 User Flow
1. User aktiviert Push im Browser
2. Browser Permission Request
3. Subscription wird an Backend gesendet
4. Backend speichert PushSubscription in `notification_channels`
5. Bei Events: Backend sendet via Web Push Protocol

#### 1.4 Backend Erweiterungen
- `web-push` PHP Library (minishlink/web-push)
- VAPID Keys Generierung
- Push Endpoint in NotificationService
- Batch-Sending für Performance

#### 1.5 Trigger Events
- Kalender Erinnerungen
- Kanban Deadlines
- Neue Nachrichten (Chat)
- Uptime Alerts
- Recurring Tasks fällig
- Ticket Updates

### Datenbank Migration
```sql
-- Erweiterung notification_channels
ALTER TABLE notification_channels
MODIFY channel_type ENUM('in_app', 'email', 'webhook', 'slack', 'telegram', 'web_push') NOT NULL;

-- Web Push Subscriptions
CREATE TABLE web_push_subscriptions (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh_key VARCHAR(255) NOT NULL,
    auth_key VARCHAR(255) NOT NULL,
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## 2. Backup & Recovery System

### Status: Geplant
### Priorität: Kritisch (Self-Hosting)

### Beschreibung
Automatisches Backup-System für Datenbank und Dateien mit Restore-Funktion.

### Features

#### 2.1 Backup-Typen
- **Vollbackup**: Komplette Datenbank + Storage-Dateien
- **Inkrementell**: Nur Änderungen seit letztem Backup
- **Datenbank-only**: Nur MySQL Dump

#### 2.2 Backup-Ziele
- **Lokal**: `/backups` Verzeichnis
- **S3-kompatibel**: AWS S3, MinIO, Backblaze B2
- **SFTP/SCP**: Remote Server
- **Nextcloud/WebDAV**: Cloud Storage

#### 2.3 Scheduling
- Täglich, Wöchentlich, Monatlich
- Cron-basiert (Backend Cronjob)
- Retention Policy (z.B. "30 Tage behalten")

#### 2.4 Monitoring
- Backup Health Dashboard Widget
- Email bei Backup-Fehler
- Letzte erfolgreiche Backups anzeigen
- Speicherplatz-Warnung

#### 2.5 Restore
- One-Click Restore (mit Bestätigung)
- Point-in-Time Recovery
- Selective Restore (nur bestimmte Tabellen)

### Datenbank Schema
```sql
CREATE TABLE backups (
    id VARCHAR(36) PRIMARY KEY,
    type ENUM('full', 'incremental', 'database') NOT NULL,
    status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    storage_target VARCHAR(50) NOT NULL,
    file_path TEXT,
    file_size BIGINT,
    checksum VARCHAR(64),
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE backup_schedules (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('full', 'incremental', 'database') NOT NULL,
    storage_target VARCHAR(50) NOT NULL,
    storage_config JSON,
    cron_expression VARCHAR(100) NOT NULL,
    retention_days INT DEFAULT 30,
    is_enabled BOOLEAN DEFAULT TRUE,
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE backup_storage_targets (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('local', 's3', 'sftp', 'webdav') NOT NULL,
    config JSON NOT NULL, -- encrypted credentials
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### API Endpoints
```
GET    /api/v1/backups                    - Liste aller Backups
POST   /api/v1/backups                    - Manuelles Backup starten
GET    /api/v1/backups/{id}               - Backup Details
DELETE /api/v1/backups/{id}               - Backup löschen
POST   /api/v1/backups/{id}/restore       - Restore starten

GET    /api/v1/backup-schedules           - Alle Schedules
POST   /api/v1/backup-schedules           - Schedule erstellen
PUT    /api/v1/backup-schedules/{id}      - Schedule bearbeiten
DELETE /api/v1/backup-schedules/{id}      - Schedule löschen

GET    /api/v1/backup-targets             - Storage Targets
POST   /api/v1/backup-targets             - Target erstellen
POST   /api/v1/backup-targets/{id}/test   - Verbindung testen
```

---

## 3. Link Shortener

### Status: Geplant
### Priorität: Mittel

### Beschreibung
Self-hosted URL-Kürzungsdienst mit Statistiken.

### Features

#### 3.1 URL Kürzung
- Automatischer Short-Code (6 Zeichen)
- Custom Short-Code (optional)
- Ablaufdatum (optional)
- Passwortschutz (optional)
- Max. Klicks Limit (optional)

#### 3.2 Statistiken
- Klick-Zähler
- Referrer Tracking
- Browser/OS Statistiken
- Geografische Daten (IP-basiert)
- Zeitliche Verteilung (Chart)

#### 3.3 QR-Code
- Automatische QR-Code Generierung (Tool existiert bereits!)
- Download als PNG/SVG
- Anpassbare Größe

#### 3.4 Integration
- API für externe Nutzung
- Browser Extension (optional)
- Bookmarklet

### Datenbank Schema
```sql
CREATE TABLE short_links (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    short_code VARCHAR(20) NOT NULL UNIQUE,
    original_url TEXT NOT NULL,
    title VARCHAR(255),
    description TEXT,
    password_hash VARCHAR(255),
    expires_at TIMESTAMP NULL,
    max_clicks INT NULL,
    click_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_short_code (short_code),
    INDEX idx_user (user_id)
);

CREATE TABLE short_link_clicks (
    id VARCHAR(36) PRIMARY KEY,
    link_id VARCHAR(36) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer TEXT,
    country_code VARCHAR(2),
    city VARCHAR(100),
    browser VARCHAR(50),
    os VARCHAR(50),
    device_type ENUM('desktop', 'mobile', 'tablet', 'bot', 'unknown') DEFAULT 'unknown',
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (link_id) REFERENCES short_links(id) ON DELETE CASCADE,
    INDEX idx_link (link_id),
    INDEX idx_clicked (clicked_at)
);
```

### API Endpoints
```
GET    /api/v1/links                      - Alle Links
POST   /api/v1/links                      - Link erstellen
GET    /api/v1/links/{id}                 - Link Details
PUT    /api/v1/links/{id}                 - Link bearbeiten
DELETE /api/v1/links/{id}                 - Link löschen
GET    /api/v1/links/{id}/stats           - Statistiken
GET    /api/v1/links/{id}/qr              - QR-Code generieren

GET    /s/{code}                          - Redirect (öffentlich)
```

### Frontend Route
```
/links                                    - Link Manager
/s/:code                                  - Public Redirect
```

---

## 4. Dashboard Widget Erweiterungen

### Status: Geplant
### Priorität: Mittel

### Bestehende Widgets
- Quick Stats ✅
- Recent Tasks ✅
- Recent Documents ✅
- Uptime Status ✅
- Time Tracking Today ✅
- Kanban Summary ✅
- Productivity Chart ✅
- Calendar Preview ✅
- Quick Notes ✅
- Recent Activity ✅

### Neue Widgets

#### 4.1 Wetter Widget
```javascript
widget_type: 'weather'
config: {
  location: 'Berlin, DE',     // User kann Ort selbst setzen
  latitude: 52.52,            // oder Koordinaten
  longitude: 13.405,
  units: 'metric',            // metric/imperial
  show_forecast: true,        // 3-Tage Vorschau
}
```

**Features:**
- Aktuelles Wetter (Temperatur, Icon, Beschreibung)
- 3-Tage Vorhersage
- Sunrise/Sunset
- Luftfeuchtigkeit, Wind
- Ortseingabe mit Autocomplete
- OpenWeatherMap API (kostenloser Tier)

**Backend:**
- API Key in System-Settings
- Caching (15 Min)
- Geocoding für Ortssuche

#### 4.2 Countdown Widget
```javascript
widget_type: 'countdown'
config: {
  title: 'Urlaub',
  target_date: '2025-07-15T00:00:00',
  show_time: true,
  color: 'green',
}
```

**Features:**
- Countdown zu beliebigem Datum
- Tage/Stunden/Minuten/Sekunden
- Mehrere Countdowns möglich
- Farbauswahl
- Benachrichtigung wenn erreicht

#### 4.3 Bookmarks Widget
```javascript
widget_type: 'bookmarks_quick'
config: {
  group_id: null,             // Optional: nur bestimmte Gruppe
  max_items: 8,
}
```

**Features:**
- Schnellzugriff auf Lesezeichen
- Favicon Anzeige
- Gruppierung
- Drag & Drop Sortierung

#### 4.4 System Status Widget
```javascript
widget_type: 'system_status'
config: {
  show_cpu: true,
  show_memory: true,
  show_disk: true,
}
```

**Features:**
- Server CPU/RAM/Disk Auslastung
- Docker Container Status
- Datenbank-Größe
- Nur für Owner/Admin

#### 4.5 Habit Tracker Widget (Mini)
```javascript
widget_type: 'habits_mini'
config: {
  habits: ['Sport', 'Lesen', 'Meditation'],
}
```

**Features:**
- Heutige Habits abhaken
- Streak-Anzeige
- Schnelle Eingabe

### Datenbank Erweiterung
```sql
-- Wetter Cache
CREATE TABLE weather_cache (
    id VARCHAR(36) PRIMARY KEY,
    location_key VARCHAR(100) NOT NULL UNIQUE,
    data JSON NOT NULL,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_location (location_key),
    INDEX idx_expires (expires_at)
);

-- System Settings für API Keys
INSERT INTO system_settings (key, value, category) VALUES
('openweathermap_api_key', '', 'integrations');
```

---

## 5. Automation / Workflow Engine (IFTTT-Style)

### Status: Geplant
### Priorität: Hoch

### Beschreibung
Regel-basiertes Automatisierungssystem, das verschiedene Module verbindet.

### Konzept

```
WENN [Trigger]
DANN [Aktion(en)]
(OPTIONAL: Filter/Bedingungen)
```

### Verfügbare Trigger

| Modul | Trigger | Daten |
|-------|---------|-------|
| Kanban | Karte erstellt | card, board, column |
| Kanban | Karte verschoben | card, from_column, to_column |
| Kanban | Karte erledigt | card, board |
| Kalender | Event beginnt in X min | event |
| Kalender | Event erstellt | event |
| Uptime | Host offline | host, downtime |
| Uptime | Host wieder online | host, downtime_duration |
| Tickets | Neues Ticket | ticket |
| Tickets | Ticket-Status geändert | ticket, old_status, new_status |
| Time | Timer gestartet | entry, project |
| Time | Timer gestoppt | entry, duration |
| Chat | Nachricht empfangen | message, sender |
| Webhook | Externer Webhook | payload |
| Cron | Zeitplan | datetime |
| Storage | Datei hochgeladen | file |

### Verfügbare Aktionen

| Modul | Aktion | Parameter |
|-------|--------|-----------|
| Notification | Benachrichtigung senden | title, message, channels |
| Kanban | Karte erstellen | board_id, title, column |
| Kanban | Karte verschieben | card_id, column_id |
| Kanban | Label hinzufügen | card_id, tag_id |
| Lists | Aufgabe erstellen | list_id, content |
| Documents | Dokument erstellen | title, content |
| Webhook | HTTP Request | url, method, headers, body |
| Email | Email senden | to, subject, body |
| Chat | Nachricht senden | message |
| Calendar | Event erstellen | title, date, description |

### Beispiel-Automationen

```yaml
# Wenn Kanban-Karte in "Done" verschoben wird → Benachrichtigung
name: "Karte erledigt"
trigger:
  type: kanban.card_moved
  conditions:
    to_column_name: "Done"
actions:
  - type: notification.send
    params:
      title: "Karte erledigt!"
      message: "{{card.title}} wurde abgeschlossen"
      channels: [in_app, web_push]

# Wenn Uptime-Host offline → Slack Nachricht
name: "Server Down Alert"
trigger:
  type: uptime.host_down
actions:
  - type: webhook.request
    params:
      url: "https://hooks.slack.com/..."
      method: POST
      body: '{"text": "🔴 {{host.name}} ist offline!"}'

# Jeden Montag um 9:00 → Wochenübersicht
name: "Wochenstart Reminder"
trigger:
  type: cron
  schedule: "0 9 * * 1"
actions:
  - type: notification.send
    params:
      title: "Neue Woche!"
      message: "Du hast {{open_tasks_count}} offene Aufgaben"
```

### Datenbank Schema
```sql
CREATE TABLE automations (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    trigger_type VARCHAR(100) NOT NULL,
    trigger_config JSON NOT NULL,
    conditions JSON, -- Filter-Bedingungen
    is_enabled BOOLEAN DEFAULT TRUE,
    run_count INT DEFAULT 0,
    last_run_at TIMESTAMP NULL,
    last_error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_trigger (trigger_type)
);

CREATE TABLE automation_actions (
    id VARCHAR(36) PRIMARY KEY,
    automation_id VARCHAR(36) NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    action_config JSON NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (automation_id) REFERENCES automations(id) ON DELETE CASCADE,
    INDEX idx_automation (automation_id)
);

CREATE TABLE automation_logs (
    id VARCHAR(36) PRIMARY KEY,
    automation_id VARCHAR(36) NOT NULL,
    trigger_data JSON,
    status ENUM('success', 'failed', 'partial') NOT NULL,
    actions_executed JSON,
    error_message TEXT,
    execution_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (automation_id) REFERENCES automations(id) ON DELETE CASCADE,
    INDEX idx_automation (automation_id),
    INDEX idx_created (created_at)
);
```

### API Endpoints
```
GET    /api/v1/automations                - Alle Automationen
POST   /api/v1/automations                - Automation erstellen
GET    /api/v1/automations/{id}           - Details
PUT    /api/v1/automations/{id}           - Bearbeiten
DELETE /api/v1/automations/{id}           - Löschen
POST   /api/v1/automations/{id}/toggle    - Aktivieren/Deaktivieren
POST   /api/v1/automations/{id}/test      - Manuell ausführen
GET    /api/v1/automations/{id}/logs      - Ausführungs-Logs

GET    /api/v1/automations/triggers       - Verfügbare Trigger
GET    /api/v1/automations/actions        - Verfügbare Aktionen
```

### Frontend
```
/automations                              - Automations Manager
```

**UI Features:**
- Visual Builder (Drag & Drop)
- Trigger-Auswahl mit Beschreibung
- Aktionen-Kette
- Variablen-Picker ({{card.title}})
- Test-Modus
- Logs & Debug

---

## Implementierungs-Reihenfolge (Empfehlung)

| Phase | Feature | Aufwand | Begründung |
|-------|---------|---------|------------|
| 1 | Backup & Recovery | Mittel | Kritisch für Self-Hosting |
| 2 | Web Push Notifications | Niedrig | Erweitert bestehendes System |
| 3 | Dashboard Widgets | Niedrig | Schnelle Verbesserung |
| 4 | Link Shortener | Mittel | Standalone, wenig Abhängigkeiten |
| 5 | Automation Engine | Hoch | Komplex, aber sehr wertvoll |

---

## Technische Voraussetzungen

### Web Push
- VAPID Keys generieren
- Service Worker im Frontend
- `minishlink/web-push` PHP Library

### Backup
- Shell-Zugriff (mysqldump)
- S3-SDK: `aws/aws-sdk-php`
- Cronjob für Scheduler

### Wetter Widget
- OpenWeatherMap API Key (kostenlos bis 1000 calls/Tag)
- Caching Layer (bereits via Redis vorhanden)

### Automations
- Event-Dispatcher System
- Queue für asynchrone Ausführung (optional Redis Queue)
- Template-Engine für Variablen (Twig oder einfacher Parser)

---

*Dokument erstellt: 2025-12-12*
*Letzte Aktualisierung: 2025-12-12*
