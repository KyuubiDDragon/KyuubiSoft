# Alexa-Integration & Status-API

Diese Integration stellt **abgesicherte, read-only Status-Endpunkte** bereit
(lokaler Server + lokale Docker-Container + Dienste) und einen **self-hosted
Alexa-Skill-Endpunkt**, damit ein Echo Show 8 den Serverzustand vorlesen und
anzeigen kann.

Alle Daten sind **lokal** (lokaler Docker-Daemon, lokaler Host) und **nur
lesend**. Es wird nichts gestartet, gestoppt oder verändert.

---

## 1. Status-API (für Alexa, Dashboards, n8n, Home Assistant …)

Authentifizierung wahlweise per JWT (Web-UI) oder per **API-Key** mit dem Scope
`status.read`. Der Key kommt in den Header `X-API-Key`.

| Endpunkt | Zweck |
|---|---|
| `GET /api/v1/status/overview` | Kompakte Zusammenfassung (Server + Docker + Dienste + Alerts) — für Sprache/Dashboard |
| `GET /api/v1/status/server` | Host: CPU %, RAM, Disks, Load, Uptime |
| `GET /api/v1/status/containers` | Lokale Container inkl. Live-CPU/RAM |
| `GET /api/v1/status/services` | Uptime-Monitore, SSL-Zertifikate, Cron-Jobs |
| `POST /api/v1/status/containers/{name}/{action}` | Container steuern (`start`/`stop`/`restart`) — Scope `status.write` |

Uptime/SSL/Cron sind auf den Benutzer des Keys beschränkt. Der Steuer-Endpunkt
ist bewusst getrennt: ein reiner `status.read`-Key kann ihn nicht erreichen.

```bash
# Container neu starten (Schreibrecht nötig)
curl -X POST https://deine-domain.tld/api/v1/status/containers/nginx/restart \
  -H "X-API-Key: ks_...schreib-key..."
```

### Beispiel

```bash
curl -s https://deine-domain.tld/api/v1/status/overview \
  -H "X-API-Key: ks_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
```

Beispielantwort (gekürzt):

```json
{
  "success": true,
  "data": {
    "generated_at": "2026-07-07T10:00:00+00:00",
    "server": {
      "cpu": { "percent": 23.4, "cores": 8, "load": [0.5, 0.4, 0.3] },
      "memory": { "total_bytes": 16777216000, "used_bytes": 10200000000, "percent": 61.0 },
      "disks": [ { "mount": "/", "percent": 92.0, "used_bytes": 0, "total_bytes": 0 } ],
      "uptime": "up 4 days"
    },
    "docker": { "available": true, "summary": { "total": 7, "running": 6, "stopped": 1, "unhealthy": 1 } },
    "services": {
      "uptime": { "total": 3, "up": 2, "down": 1, "down_names": ["Webshop"] },
      "ssl": { "total": 2, "valid": 1, "expiring_soon": 1, "expired": 0, "problem_names": ["mail.tld"] },
      "cron": { "total": 4, "failed": 1, "failed_names": ["Backup"] }
    },
    "alerts": [
      { "level": "critical", "message": "Festplatte / ist zu 92% voll" },
      { "level": "critical", "message": "Offline: Webshop" }
    ]
  }
}
```

### API-Key anlegen

Ein Key mit ausschließlich `status.read` ist minimal berechtigt (read-only,
kann keine Kontroll-Endpunkte erreichen). Erzeugung über die bestehende
`api-keys`-Verwaltung (`POST /api/v1/api-keys`, Feld `scopes: ["status.read"]`).
Setze zusätzlich ein `expires_at`. Der Key wird **nur einmal** angezeigt.

---

## 2. Alexa Skill (self-hosted)

Der Skill nutzt **deinen eigenen HTTPS-Endpunkt** als Backend — kein AWS Lambda
nötig. Jede Anfrage wird gegen die **Amazon-Signatur + Skill-ID + Zeitstempel**
geprüft; nur echte Anfragen deines Skills werden verarbeitet.

**Endpunkt:** `POST https://deine-domain.tld/api/v1/alexa/webhook`

### Einrichtung in der Alexa Developer Console

1. **Skill anlegen:** Custom Skill, Sprache **Deutsch (DE)**, Hosting-Methode
   **„Provide your own HTTPS endpoint"**.
2. **Interaction Model:** Unter *Build → JSON Editor* den Inhalt von
   [`interaction-model.json`](./interaction-model.json) einfügen und *Build Model*.
   Den Invocation-Namen (`server status`) bei Bedarf anpassen.
3. **Endpoint:** *Endpoint → HTTPS* → obige URL. Als Zertifikatstyp
   „My development endpoint has a certificate from a trusted certificate
   authority" wählen (du hast ein gültiges Domain-Zertifikat).
4. **Skill-ID kopieren** (`amzn1.ask.skill.…`) und in deiner `.env` setzen:

   ```env
   ALEXA_SKILL_ID=amzn1.ask.skill.xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
   # optional: Uptime/SSL/Cron auf einen Benutzer beschränken
   ALEXA_USER_ID=
   ```

5. Skill auf demselben Amazon-Konto testen, mit dem dein Echo Show 8 verknüpft
   ist. Beispiele:
   - „Alexa, öffne server status."
   - „Alexa, frag server status, wie es dem Server geht."
   - „… wie voll ist die Festplatte?"
   - „… laufen alle Container?"
   - „… sind alle Dienste online?"

Auf dem Show 8 wird zusätzlich eine kompakte **APL-Ansicht** (CPU/RAM, Container,
Uptime, Alerts) angezeigt.

### Sicherheitsmodell

- Der Endpunkt ist öffentlich erreichbar (muss er, damit Amazon ihn erreicht),
  verarbeitet aber **nur signaturgeprüfte** Anfragen deines Skills.
- Signaturprüfung (`AlexaRequestVerifier`): HTTPS-Cert-Chain-URL auf
  `s3.amazonaws.com/echo.api/`, Zertifikatsgültigkeit + `echo-api.amazon.com`
  SAN, RSA-SHA256-Signatur über den **rohen** Request-Body, Zeitstempel
  ±150 s (Replay-Schutz), Skill-ID-Abgleich.
- `ALEXA_SKIP_VERIFICATION=true` deaktiviert die Prüfung — **nur** für lokale
  Tests und **nur** wenn `APP_ENV` ≠ `production`.

---

## 3. Steuerung per Sprache (start/stop/restart)

Container lassen sich per Sprache steuern — **standardmäßig deaktiviert** und
mit **gesprochener Bestätigung** abgesichert.

Aktivierung in der `.env`:

```env
ALEXA_ALLOW_CONTROL=true
# optional: nur diese Container per Sprache steuerbar (sonst alle)
ALEXA_CONTROL_ALLOWLIST=nginx,webshop,gameserver
```

Ablauf (Beispiel):

1. „Alexa, sag server status, starte den Container Nginx neu."
2. Alexa: „Soll ich den Container Nginx wirklich neu starten? Sag ja oder nein."
3. „Ja." → Alexa: „Container Nginx wurde neu gestartet."

Sicherheitsnetz:

- Steuern ist **opt-in** (`ALEXA_ALLOW_CONTROL`), sonst nur Lesen.
- Jede destruktive Aktion verlangt eine **gesprochene Bestätigung**; die
  ausstehende Aktion wird nur in den Session-Attributen gehalten.
- Beim „Ja" werden Freigabe **und** Allowlist erneut geprüft — die Session
  allein wird nie als Berechtigung akzeptiert.
- Optionale **Allowlist** begrenzt, welche Container überhaupt steuerbar sind.
- Der zugrunde liegende REST-Endpunkt verlangt den separaten Scope
  `status.write` (ein `status.read`-Key kann nicht steuern).
