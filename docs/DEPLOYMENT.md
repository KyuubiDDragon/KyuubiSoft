# KyuubiSoft Deployment Guide

## Übersicht

Diese Anleitung beschreibt das Deployment von KyuubiSoft mit:
- **GitHub Actions** - Automatischer Image-Build
- **GitHub Container Registry (ghcr.io)** - Image Hosting
- **Portainer CE** - Container Management
- **Plesk Docker Proxy Rules** - Domain + SSL

## Architektur

```
┌─────────────────────────────────────────────────────────────┐
│                        GitHub                                │
│  ┌─────────────────┐    ┌─────────────────────────────────┐ │
│  │   Repository    │───▶│   GitHub Actions                │ │
│  │   (Source)      │    │   (Build Images on Push)        │ │
│  └─────────────────┘    └───────────────┬─────────────────┘ │
│                                         │                    │
│                                         ▼                    │
│                         ┌─────────────────────────────────┐ │
│                         │   ghcr.io (Container Registry)  │ │
│                         │   - kyuubisoft-frontend:latest  │ │
│                         │   - kyuubisoft-backend:latest   │ │
│                         └───────────────┬─────────────────┘ │
└─────────────────────────────────────────┼───────────────────┘
                                          │ pull
                                          ▼
┌─────────────────────────────────────────────────────────────┐
│                      Dein Server                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  Plesk (dev.kyuubisoft.com:443)                         ││
│  │  └── Docker Proxy Rule → kyuubisoft_nginx:8080          ││
│  │  └── Let's Encrypt SSL                                  ││
│  └─────────────────────────────────────────────────────────┘│
│                              │                               │
│                              ▼                               │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  Docker (Portainer)                                     ││
│  │  ┌─────────────────────────────────────────────────────┐││
│  │  │  KyuubiSoft Stack                                   │││
│  │  │  ├── nginx (:8080)        ← Plesk verbindet hierher │││
│  │  │  ├── frontend (ghcr.io)                             │││
│  │  │  ├── backend (ghcr.io)                              │││
│  │  │  ├── mysql (:3306 intern)                           │││
│  │  │  └── redis (:6379 intern)                           │││
│  │  └─────────────────────────────────────────────────────┘││
│  └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

---

## Schritt 1: GitHub Repository vorbereiten

### 1.1 Branch mergen

Falls noch nicht geschehen, merge den Feature-Branch in `main`:
1. GitHub → Pull Requests → New
2. `base: main` ← `compare: feature-branch`
3. Create Pull Request → Merge

### 1.2 GitHub Actions prüfen

Nach dem Merge startet automatisch der Build:
1. GitHub → Actions Tab
2. Warte bis "Build and Push Docker Images" grün ist
3. Prüfe unter Packages ob die Images existieren:
   - `ghcr.io/kyuubiddragon/kyuubisoft-frontend`
   - `ghcr.io/kyuubiddragon/kyuubisoft-backend`

### 1.3 Packages öffentlich machen (einmalig)

1. GitHub → Packages (rechte Sidebar)
2. Für jedes Package:
   - Package Settings → Danger Zone
   - Change visibility → Public

Oder via Repository Settings → Packages → Inherit access from source repository

---

## Schritt 2: Portainer Stack erstellen

### 2.1 In Portainer einloggen

1. Öffne Portainer: `https://portainer.deinserver.de`
2. Wähle deine Docker-Umgebung

### 2.2 Stack erstellen

1. **Stacks** → **Add stack**
2. **Name:** `kyuubisoft`
3. **Build method:** Repository

### 2.3 Repository konfigurieren

```
Repository URL: https://github.com/KyuubiDDragon/KyuubiSoft
Repository reference: refs/heads/main
Compose path: docker-compose.prod.yml
```

### 2.4 Environment Variables

Klicke auf **Add environment variable** und füge hinzu:

| Variable | Wert |
|----------|------|
| `DB_DATABASE` | `kyuubisoft` |
| `DB_USERNAME` | `kyuubisoft` |
| `DB_PASSWORD` | `<SICHERES_PASSWORT>` |
| `MYSQL_ROOT_PASSWORD` | `<SICHERES_ROOT_PASSWORT>` |
| `JWT_SECRET` | `<GENERIERTER_KEY>` |
| `CORS_ALLOWED_ORIGINS` | `https://dev.kyuubisoft.com` |

**JWT Secret generieren:**
```bash
openssl rand -base64 32
```

### 2.5 Stack deployen

Klicke auf **Deploy the stack**

**Kein Build nötig!** Portainer zieht die fertigen Images von ghcr.io.

---

## Schritt 3: Plesk Docker Proxy Rule

### 3.1 Docker Proxy Rule erstellen

1. Plesk → **Docker** → **Docker Proxy Rules**
2. Klicke **Add Rule**
3. Konfiguriere:

| Feld | Wert |
|------|------|
| Domain | `dev.kyuubisoft.com` |
| Container | `kyuubisoft_nginx` |
| Container Port | `8080` |
| SSL/TLS support | ✓ aktivieren |

4. Klicke **OK**

### 3.2 SSL aktivieren

Falls noch nicht automatisch:
1. Plesk → dev.kyuubisoft.com → **SSL/TLS Certificates**
2. **Let's Encrypt** → Ausstellen

---

## Schritt 4: Datenbank initialisieren

### Via Portainer Console

1. Portainer → Stacks → kyuubisoft
2. Container `kyuubisoft_backend` → **Console**
3. Connect mit `/bin/sh`
4. Ausführen:

```bash
php bin/migrate.php --seed
```

### Via SSH

```bash
docker exec -it kyuubisoft_backend php bin/migrate.php --seed
```

---

## Schritt 5: Testen

1. Öffne `https://dev.kyuubisoft.com`
2. Registriere dich
3. Login testen

### Admin-Rechte vergeben

```bash
# In MySQL Console
docker exec -it kyuubisoft_mysql mysql -u kyuubisoft -p kyuubisoft
```

```sql
-- User-ID finden
SELECT id, email FROM users;

-- Owner-Rolle zuweisen
INSERT INTO user_roles (user_id, role_id, assigned_at)
SELECT 'DEINE-UUID', id, NOW() FROM roles WHERE name = 'owner';
```

---

## Schritt 6: Auto-Deploy einrichten (Optional)

### 6.1 Portainer Webhook

1. Portainer → Stacks → kyuubisoft → Stack details
2. Kopiere die **Webhook URL**
3. Aktiviere "GitOps updates" falls gewünscht

### 6.2 GitHub Repository Variable

1. GitHub → Repository → Settings → Secrets and variables → Actions
2. Variables Tab → New repository variable
3. Name: `PORTAINER_WEBHOOK_URL`
4. Value: Die kopierte Webhook URL

Jetzt triggert jeder Push zu `main`:
1. GitHub Actions baut neue Images
2. Webhook benachrichtigt Portainer
3. Portainer zieht die neuen Images

---

## Updates durchführen

### Manuell
1. Portainer → Stacks → kyuubisoft
2. Klicke **Pull and redeploy**

### Automatisch
Push zu `main` → GitHub Actions → Portainer Webhook

---

## Troubleshooting

### Images werden nicht gefunden (403/404)

Die GitHub Packages müssen öffentlich sein:
1. GitHub → Packages
2. Package Settings → Change visibility → Public

### Container startet nicht

```bash
docker logs kyuubisoft_backend
docker logs kyuubisoft_nginx
docker logs kyuubisoft_mysql
```

### 502 Bad Gateway

1. Prüfe ob alle Container laufen: `docker ps`
2. Prüfe Plesk Docker Proxy Rule
3. Prüfe ob Port 8080 korrekt ist

### CORS Fehler

Prüfe `CORS_ALLOWED_ORIGINS` Environment Variable.

### MySQL Healthcheck failed

Warte 30 Sekunden - MySQL braucht Zeit zum Starten.

---

## Ports-Übersicht

| Service | Port | Zugriff |
|---------|------|---------|
| Nginx | 8080 | Plesk Proxy |
| Backend | 9000 | Nur intern |
| MySQL | 3306 | Nur intern |
| Redis | 6379 | Nur intern |

---

## Sicherheits-Checkliste

- [ ] Sichere Passwörter für MySQL
- [ ] JWT Secret mit `openssl rand -base64 32` generiert
- [ ] HTTPS via Let's Encrypt aktiv
- [ ] GitHub Packages öffentlich (oder PAT konfiguriert)
- [ ] APP_DEBUG=false in Production
- [ ] Regelmäßige Backups eingerichtet
