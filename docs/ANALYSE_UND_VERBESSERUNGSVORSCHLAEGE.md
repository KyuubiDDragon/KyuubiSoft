# KyuubiSoft – Vollständige Analyse & Verbesserungsvorschläge

**Stand:** Februar 2026
**Stack:** Vue 3 / Pinia / TailwindCSS / Vite (Frontend) • PHP 8.3 / Slim 4 / Doctrine DBAL (Backend) • MySQL 8 + Redis • Docker • GitHub Actions

---

## 1. Code-Qualität – Konkrete Probleme

### Backend

#### A. AIService.php (~1000 Zeilen) – zu monolithisch

- **Problem:** `new AIToolsService()` direkt im Konstruktor instanziiert – verletzt das Dependency Injection Prinzip
- **Problem:** Rohe cURL-Aufrufe statt Guzzle HTTP Client – fehleranfällig, kein Retry, keine Middleware
- **Problem:** `date('Y-m-d H:i:s')` zigfach dupliziert statt eines zentralen Zeitdienstes
- **Problem:** AES-256-CBC Encryption nutzt `APP_KEY` direkt als Verschlüsselungskey ohne PBKDF2-Ableitung → möglicherweise unsichere Key-Länge
- **Problem:** `callOpenAI()` und `callOpenRouter()` sind fast identisch (nahezu 100 % Code-Duplizierung) – Strategy Pattern fehlt
- **Empfehlung:** Aufteilen in `AIProviderInterface`, `OpenAIProvider`, `AnthropicProvider`, `OllamaProvider` usw.

#### B. Fehlender HTTP-Client

- Kein Guzzle/Symfony HttpClient in `composer.json` – alle externen API-Calls gehen über rohe cURL
- **Empfehlung:** `guzzlehttp/guzzle` oder `symfony/http-client` hinzufügen

#### C. Fehlende Tests

- PHPUnit `^10.5` in devDependencies, PHPStan Level 6 konfiguriert
- Keine tatsächlichen Tests im `tests/`-Verzeichnis vorhanden
- **Empfehlung:** Unit Tests für Services, Integration Tests für Controller

#### D. Migrations-Benennung

- Migrationen als `001_create_...sql` bis `08x_...sql` – keine Timestamps
- Schwer zu verwalten in Teams / paralleler Entwicklung
- **Empfehlung:** Timestamp-basierte Migrations (z. B. wie Laravel)

#### E. Discord User-Token Speicherung

- Discord User-Tokens werden gespeichert – verstößt gegen Discord ToS (Self-Botting)
- **Empfehlung:** Ausschließlich Discord Bot-API + OAuth2 (User Auth Flow) nutzen

---

### Frontend

#### A. DashboardView.vue (~1424 Zeilen) – katastrophal monolithisch

- Alle Widget-Typen als riesige `v-else-if`-Kette (12+ Widget-Typen)
- Jeder neue Widget-Typ macht die Datei größer
- **Empfehlung:** Jedes Widget als eigene Komponente (`QuickStatsWidget.vue`, `WeatherWidget.vue` etc.) + dynamisches Component-Loading via `<component :is="widgetComponents[widget.widget_type]" />`

#### B. Kein TypeScript

- Gesamtes Frontend ist reines JavaScript – keine Typsicherheit
- Fehler werden erst zur Laufzeit entdeckt
- **Empfehlung:** Schrittweise Migration auf TypeScript (zuerst Stores, dann API-Clients, dann Komponenten)

#### C. i18n – installiert aber nicht genutzt

- `vue-i18n` ist in `package.json` enthalten
- Alle Texte sind hardcoded auf Deutsch
- **Empfehlung:** Konsequente i18n-Nutzung (EN/DE als Basis), Texte aus Komponenten in Locale-Dateien auslagern

#### D. React in einem Vue-Projekt

- `react` und `react-dom` (`^18.3.1`) als Dependencies – nur für `@univerjs/presets` (Spreadsheet)
- Verdoppelt das Bundle-Konzept, erhöht Bundle-Größe signifikant
- **Empfehlung:** UniversJS entfernen oder iframe-isoliert hosten, wenn die Spreadsheet-Funktion beibehalten werden soll

#### E. Collaboration Server (CommonJS vs. ES Modules)

- `collaboration/server.js` nutzt CommonJS (`require()`), während alles andere ESM ist
- Kein TypeScript, keine Tests
- **Empfehlung:** Auf ESM und TypeScript migrieren oder durch einen Go-/Rust-Yjs-kompatiblen Server ersetzen (z. B. `y-sweet`)

---

## 2. Architektur – Verbesserungsvorschläge

### A. Widget-System neu denken

**Aktuell:** Alle Widget-Typen hardcoded in `DashboardView.vue`
**Ziel:** Widget-Registry + Dynamic Component Loading

```
/frontend/src/modules/dashboard/widgets/
  QuickStatsWidget.vue
  WeatherWidget.vue
  CountdownWidget.vue
  ...
  widgetRegistry.js  ← dynamische Komponenten-Map
```

### B. AI Provider Strategy Pattern (Backend)

**Aktuell:** Monolithische `AIService`-Klasse mit Switch/Case
**Ziel:** Interface + separate Provider-Klassen

```
AIProviderInterface
  ├── OpenAIProvider
  ├── AnthropicProvider
  ├── OpenRouterProvider
  ├── OllamaProvider
  └── CustomProvider
```

### C. Event-Dispatcher System

- In Konzept dokumentiert, aber nicht implementiert
- Wird für Automation/Workflow-Engine benötigt
- **Empfehlung:** Symfony EventDispatcher oder eigenes Event-System implementieren
- Ermöglicht lose Kopplung zwischen Modulen (z. B. „Uptime Host offline" → Notification)

### D. Queue/Job System

- Background Worker vorhanden, aber unklar ob tatsächlich Queue-basiert
- Für Automation, Backup-Jobs, Benachrichtigungen benötigt
- **Empfehlung:** Redis-Queue mit Supervisor (oder Laravel Horizon-ähnliches System für PHP)

### E. Frontend Store-Konsolidierung

- Sehr viele einzelne Pinia Stores (20+)
- Keine klare Namenskonvention (`stores/auth.js` vs. `stores/features.js` vs. `modules/mockup-editor/stores/mockupStore.js`)
- **Empfehlung:** Konsistente Struktur – alle Stores entweder in `/stores/` oder in den jeweiligen Modulen

---

## 3. Sicherheit – Kritische Punkte

| Problem | Schwere | Empfehlung |
|---------|---------|------------|
| AES-CBC Key aus APP_KEY ohne Key-Ableitung | **Hoch** | `hash_hmac('sha256', APP_KEY, 'key-derivation')` → 32-Byte Key |
| Discord User Tokens gespeichert | **Hoch** | Nur Bot-OAuth2 nutzen |
| Kein CSRF-Token sichtbar (API-only = OK) | Mittel | Prüfen, ob `SameSite=Strict` auf Cookies gesetzt ist |
| Rate Limiting nur global (100 req/min) | Mittel | Endpoint-spezifische Rate Limits (z. B. 5 req/min für Auth) |
| API Keys ohne Scope-System | Niedrig | API Keys mit Scopes (read-only, write, admin) |
| YouTube Downloader – rechtliche Grauzone | Info | Klarstellung im Setup-Guide |

---

## 4. Features – Entfernen / Vereinfachen

### Komplett entfernen

- **Discord User-Token-basiertes Backup** → Ersatz durch Discord Bot-API (legitim)
- **React + @univerjs/presets** → Bundle-Bloat ohne klaren Mehrwert; iFrame-Isolierung oder externe App
- **Ratchet PHP WebSocket Server** (`cboden/ratchet`) → Node.js Collaboration Server übernimmt bereits WebSocket; Dopplung

### Vereinfachen

- **Toolbox** (25+ einzelne Mini-Tools) → Prüfen, welche wirklich genutzt werden; ggf. auf die 10 meistgenutzten reduzieren
- **Export-Modul** → PDF-Export mit html2pdf.js ist fragil; ggf. auf server-seitiges PDF (wkhtmltopdf/Puppeteer) umstellen
- **Multiple AI Provider** → Kern-Feature, aber Code-Duplikation drastisch reduzieren

---

## 5. Design / UX – Verbesserungsvorschläge

### A. Light Mode

- Aktuell: Nur Dark Mode
- **Empfehlung:** `prefers-color-scheme`-Support + manueller Toggle
- TailwindCSS `dark:`-Klassen werden bereits genutzt → Basis vorhanden

### B. Command Palette (Ctrl+K)

- Kein Global Search / Command Palette vorhanden
- **Empfehlung:** Ctrl+K öffnet eine Command Palette (à la Linear/Notion)
  - Navigieren zu Modulen
  - Neue Aufgabe erstellen
  - In Dokumenten suchen
  - Quick Actions

### C. Onboarding Wizard

- Neue User landen auf einem leeren Dashboard
- **Empfehlung:** Interaktiver Setup-Wizard bei erstem Login
  - Schritt 1: Profil einrichten
  - Schritt 2: Erste Liste erstellen
  - Schritt 3: Widgets konfigurieren

### D. Zugänglichkeit (Accessibility)

- Keine ARIA-Labels in Komponenten sichtbar
- **Empfehlung:** Keyboard-Navigation, `role=`, `aria-label=`, Screen-Reader-Support

### E. Mobile / PWA

- `vite-plugin-pwa` installiert, aber PWA wahrscheinlich nicht vollständig konfiguriert
- **Empfehlung:**
  - Service Worker für Offline-Fähigkeit
  - PWA-Manifest mit App-Icons
  - Mobile-optimierte Sidebar (Drawer statt fester Seitenleiste)
  - Touch-Gesten für Kanban

### F. Widget-System UX

- Beim Hinzufügen: Keine Vorschau des Widgets
- **Empfehlung:** Widget-Vorschau im Dialog „Widget hinzufügen"

---

## 6. Neue Features / Module

### A. Habit Tracker (Vollmodul)

- Als Mini-Widget geplant, fehlt als vollständiges Modul
- Features: Habits definieren, tägliches Einchecken, Streak-Tracking, Heatmap-Ansicht

### B. Finance / Ausgaben-Tracker

- In Konzept als „Expense Tracker" erwähnt
- Features: Kategorien, Budget, Charts, CSV-Import, wiederkehrende Ausgaben

### C. Passwort-Manager – TOTP-Unterstützung

- Passwort-Modul vorhanden, TOTP-Codes (2FA) fehlen
- **Empfehlung:** TOTP-Codes direkt im Passwort-Manager anzeigen + Auto-Copy

### D. Flashcard / Spaced Repetition

- Für lernorientierte Nutzer
- Features: Decks, Karten, SM-2-Algorithmus, Lernstatistiken

### E. Rezept-Manager

- Rezepte speichern, Zutaten, Zubereitungsschritte, Wochenpläne, Einkaufsliste-Integration

### F. Fokus-Timer / Pomodoro

- Als Toolbox-Tool vorhanden (MeetingTimer), aber kein vollständiges Modul
- Features: Pomodoro-Sessions, Integration mit Time Tracking, Statistiken

---

## 7. Neue Integrationen

| Integration | Nutzen | Aufwand |
|-------------|--------|---------|
| **CalDAV/CardDAV** | Echter Kalender-Sync (Apple Calendar, Thunderbird) | Mittel |
| **Obsidian Vault Import** | Notizen-Import via Vault-Format | Niedrig |
| **Matrix/Element** | Federated Team-Chat-Alternative | Hoch |
| **Nextcloud** | Datei-Integration, Backup-Ziel | Mittel |
| **Stripe** | Rechnungsmodul bezahlbar machen | Mittel |
| **Healthchecks.io** | Besseres Cron-Monitoring | Niedrig |
| **Authentik/Keycloak** | SSO-Unterstützung (OIDC) | Mittel |
| **GitHub Webhooks → KyuubiSoft** | PR-Status als Ticket/Aufgabe | Niedrig |
| **Grafana Embed** | System-Metriken einbetten | Niedrig |
| **ntfy.sh** | Push-Notifications ohne Firebase | Niedrig |
| **Gotify** | Self-hosted Push-Alternative | Niedrig |

---

## 8. DevOps / Infrastructure

### A. Kubernetes-Deployment

- Nur docker-compose vorhanden
- **Empfehlung:** Helm Chart für Kubernetes-Deployment

### B. Zentrales Logging

- Monolog vorhanden, aber nur File-Logging
- **Empfehlung:** Loki + Grafana oder ELK Stack als optionaler Add-on

### C. Observability

- Keine Metriken-Endpunkte
- **Empfehlung:** Prometheus-kompatible `/metrics`-Endpunkte + Grafana-Dashboard

### D. Health Checks verbessern

- MySQL Health Check vorhanden, Redis und Backend fehlen
- **Empfehlung:** `/api/v1/health` gibt Status aller Services zurück (DB, Redis, Storage)

### E. Setup Wizard (Web-basiert)

- Aktuell: `.env`-Datei manuell konfigurieren
- **Empfehlung:** Web-basierter Setup-Wizard beim Erststart (DB-Connection, Admin-Account etc.)

### F. Update-Mechanismus

- Kein automatisches Update-System
- **Empfehlung:** Docker Hub / GHCR Watchtower-Kompatibilität + Changelog im Dashboard

---

## 9. Performance

| Bereich | Problem | Lösung |
|---------|---------|--------|
| Dashboard | Widget-Daten teils sequentiell geladen | `Promise.all()` konsequent einsetzen |
| AI Context | `getUserContext()` macht 12+ separate SQL-Queries | Zu einem einzigen JOIN-Query zusammenfassen |
| Aufgaben-Counts | 3 separate `COUNT()`-Queries für Tasks | Eine Query mit `CASE WHEN` |
| Frontend Bundle | React + React-DOM enthalten | Entfernen (~150 KB gespart) |
| Bilder | Kein WebP/AVIF | Vite-Plugin für Bild-Optimierung |
| API Pagination | Nicht überall implementiert | Cursor-basierte Pagination für große Datensätze |

---

## 10. Was komplett neu schreiben?

### Collaboration Server

**Empfehlung: Ja**

- Aktuell: Node.js/CommonJS ohne TypeScript
- Alternative: `y-sweet` (Rust-basiert, Yjs-kompatibel) oder TypeScript-Rewrite
- Vorteil: Bessere Performance, weniger Wartungsaufwand

### AIService

**Empfehlung: Refactoring statt Rewrite**

- Aufteilen in Provider-Klassen + eigenen `Conversation`-Service + `Context`-Builder
- Nicht wegwerfen, nur strukturieren

### Dashboard Widget System

**Empfehlung: Ja – Frontend-Rewrite des Widget-Systems**

- Von v-else-if-Kette zu dynamischem Component-System
- Widget-Registry-Pattern
- Jeder Widget-Typ als isolierte Vue-Komponente

---

## Prioritäts-Übersicht

| Priorität | Bereich | Was |
|-----------|---------|-----|
| **Kritisch** | Sicherheit | AES-Key-Ableitung korrigieren |
| **Kritisch** | Code | AIService aufteilen (Provider Pattern) |
| **Hoch** | Code | DashboardView.vue in Widget-Komponenten aufteilen |
| **Hoch** | Code | Guzzle hinzufügen, cURL-Verwendungen ersetzen |
| **Hoch** | Feature | Light Mode |
| **Hoch** | UX | Command Palette (Ctrl+K) |
| **Mittel** | Code | TypeScript-Migration starten (Stores zuerst) |
| **Mittel** | Feature | Habit Tracker Vollmodul |
| **Mittel** | Feature | Expense Tracker |
| **Mittel** | Integration | CalDAV Support |
| **Mittel** | UX | Mobile / PWA vollständig konfigurieren |
| **Niedrig** | Code | Test-Coverage aufbauen |
| **Niedrig** | Integration | Nextcloud, Authentik, ntfy.sh |
| **Niedrig** | DevOps | Kubernetes Helm Chart |
| **Entfernen** | Code | Discord User-Token-Speicherung |
| **Entfernen** | Deps | React/ReactDOM (durch UniversJS verursacht) |
| **Entfernen** | Deps | Ratchet PHP WebSocket (redundant zum Node.js Server) |
