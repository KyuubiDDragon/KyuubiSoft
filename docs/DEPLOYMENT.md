# KyuubiSoft Deployment Guide

## Übersicht

Diese Anleitung beschreibt das Deployment von KyuubiSoft mit:
- **Portainer CE** - Container Management
- **Plesk** - Reverse Proxy + SSL (Let's Encrypt)
- **GitHub** - Source Code + Auto-Deploy via Webhook

## Architektur

```
Internet
    │
    ▼
┌─────────────────────────────────────┐
│  Plesk (dev.kyuubisoft.com:443)     │
│  └── Let's Encrypt SSL              │
│  └── Reverse Proxy → localhost:8080 │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│  Docker (Portainer)                 │
│  ┌───────────────────────────────┐  │
│  │  KyuubiSoft Stack             │  │
│  │  ├── nginx (:8080)            │  │
│  │  ├── backend (PHP-FPM :9000)  │  │
│  │  ├── mysql (:3306 intern)     │  │
│  │  └── redis (:6379 intern)     │  │
│  └───────────────────────────────┘  │
└─────────────────────────────────────┘
```

---

## Schritt 1: Portainer Stack erstellen

### 1.1 In Portainer einloggen

1. Öffne Portainer: `https://portainer.deinserver.de`
2. Wähle deine Docker-Umgebung

### 1.2 Stack erstellen

1. Gehe zu **Stacks** → **Add stack**
2. Name: `kyuubisoft`
3. **Build method**: Repository

### 1.3 Repository konfigurieren

```
Repository URL: https://github.com/KyuubiDDragon/KyuubiSoft
Repository reference: refs/heads/main
Compose path: docker-compose.prod.yml
```

### 1.4 Environment Variables

Klicke auf **Add environment variable** und füge folgende hinzu:

| Variable | Wert |
|----------|------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://dev.kyuubisoft.com` |
| `DB_DATABASE` | `kyuubisoft` |
| `DB_USERNAME` | `kyuubisoft` |
| `DB_PASSWORD` | `<SICHERES_PASSWORT>` |
| `MYSQL_ROOT_PASSWORD` | `<SICHERES_ROOT_PASSWORT>` |
| `JWT_SECRET` | `<GENERIERTER_KEY>` |
| `JWT_ACCESS_TTL` | `900` |
| `JWT_REFRESH_TTL` | `604800` |
| `CORS_ALLOWED_ORIGINS` | `https://dev.kyuubisoft.com` |
| `VITE_API_URL` | `https://dev.kyuubisoft.com/api` |

**JWT Secret generieren:**
```bash
openssl rand -base64 32
```

### 1.5 Auto-Update (Optional)

Aktiviere **GitOps updates** für automatische Updates bei GitHub Push.

### 1.6 Stack deployen

Klicke auf **Deploy the stack**

---

## Schritt 2: Plesk Reverse Proxy einrichten

### 2.1 Subdomain anlegen

1. In Plesk: **Websites & Domains** → **Add Subdomain**
2. Subdomain: `dev`
3. Domain: `kyuubisoft.com`
4. Document root: `/var/www/vhosts/kyuubisoft.com/dev.kyuubisoft.com`

### 2.2 SSL aktivieren

1. Subdomain auswählen → **SSL/TLS Certificates**
2. **Let's Encrypt** auswählen
3. Zertifikat ausstellen

### 2.3 Apache/Nginx Proxy konfigurieren

**Option A: Über Plesk UI (empfohlen)**

1. Subdomain → **Apache & nginx Settings**
2. Unter **Additional nginx directives** einfügen:

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_read_timeout 86400;
    proxy_buffering off;
}
```

**Option B: Via SSH**

Erstelle `/var/www/vhosts/system/dev.kyuubisoft.com/conf/nginx.conf`:

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

Dann:
```bash
plesk repair web dev.kyuubisoft.com
```

---

## Schritt 3: Datenbank initialisieren

Nach dem ersten Deploy müssen die Migrationen ausgeführt werden:

### Via Portainer Console

1. Portainer → Stacks → kyuubisoft
2. Container `kyuubisoft_backend` → **Console**
3. Command: `/bin/sh`
4. Ausführen:

```bash
php bin/migrate.php --seed
```

### Via SSH

```bash
docker exec -it kyuubisoft_backend php bin/migrate.php --seed
```

---

## Schritt 4: Ersten Admin-User anlegen

Nach der Migration gibt es noch keinen User. Registriere dich über die Web-UI:

1. Öffne `https://dev.kyuubisoft.com/register`
2. Registriere dich mit deiner E-Mail
3. Der erste User erhält automatisch die `user` Rolle

### Admin-Rolle zuweisen (via MySQL)

```sql
-- Finde deine User-ID
SELECT id, email FROM users;

-- Weise Owner-Rolle zu
INSERT INTO user_roles (user_id, role_id, assigned_at)
SELECT 'DEINE-USER-UUID', id, NOW() FROM roles WHERE name = 'owner';
```

**Via Portainer:**
1. Container `kyuubisoft_mysql` → Console
2. `mysql -u kyuubisoft -p kyuubisoft`
3. SQL-Befehle ausführen

---

## Schritt 5: GitHub Auto-Deploy einrichten (Optional)

### 5.1 Portainer Webhook

1. Portainer → Stacks → kyuubisoft
2. Kopiere die **Webhook URL** (unter Stack details)

### 5.2 GitHub Webhook

1. GitHub Repo → Settings → Webhooks → Add webhook
2. Payload URL: `<Portainer Webhook URL>`
3. Content type: `application/json`
4. Events: Just the push event
5. Active: ✓

Jetzt wird bei jedem Push zu `main` automatisch redeployed!

---

## Wartung

### Logs anzeigen

```bash
# Alle Container
docker compose -f docker-compose.prod.yml logs -f

# Nur Backend
docker logs -f kyuubisoft_backend

# Nur Nginx
docker logs -f kyuubisoft_nginx
```

### Backup erstellen

```bash
# MySQL Backup
docker exec kyuubisoft_mysql mysqldump -u root -p kyuubisoft > backup.sql

# Volumes sichern
docker run --rm -v kyuubisoft_mysql_data:/data -v $(pwd):/backup alpine tar czf /backup/mysql_data.tar.gz /data
```

### Update durchführen

1. Code zu GitHub pushen
2. In Portainer: Stack → **Pull and redeploy**

Oder automatisch via Webhook.

---

## Troubleshooting

### Container startet nicht

```bash
docker logs kyuubisoft_backend
docker logs kyuubisoft_nginx
```

### Datenbank-Verbindung fehlgeschlagen

1. Prüfe ob MySQL läuft: `docker ps | grep mysql`
2. Prüfe Health: `docker inspect kyuubisoft_mysql | grep Health`
3. Warte auf Healthcheck (30s beim ersten Start)

### 502 Bad Gateway

1. Prüfe ob alle Container laufen
2. Prüfe Nginx Logs
3. Prüfe Plesk Proxy Config

### CORS Fehler

Prüfe `CORS_ALLOWED_ORIGINS` in den Environment Variables.

---

## Ports-Übersicht

| Service | Interner Port | Externer Port |
|---------|---------------|---------------|
| Nginx | 80 | 8080 (localhost only) |
| Backend | 9000 | - (nur intern) |
| MySQL | 3306 | - (nur intern) |
| Redis | 6379 | - (nur intern) |

---

## Sicherheits-Checkliste

- [ ] Sichere Passwörter für MySQL
- [ ] Sicherer JWT Secret (32+ Zeichen)
- [ ] HTTPS via Let's Encrypt aktiv
- [ ] APP_DEBUG=false in Production
- [ ] Keine externen DB/Redis Ports
- [ ] Regelmäßige Backups eingerichtet
