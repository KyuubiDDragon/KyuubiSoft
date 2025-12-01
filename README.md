# KyuubiSoft

Personal Dashboard & Tools Platform - Eine modulare, sichere und erweiterbare Plattform für Techniker, Gamer und Power-User.

## Features

- **Authentifizierung**: JWT-basiert mit Refresh Tokens und 2FA (TOTP)
- **Rechtesystem**: Hierarchisches RBAC (Owner, Admin, Editor, User, Viewer)
- **Listen-Tool**: Todo-Listen, Einkaufslisten, Projekt-Listen
- **Markdown Editor**: Dokumente mit Versionierung
- **Dashboard**: Übersicht aller Aktivitäten

## Tech Stack

### Frontend
- Vue.js 3 (Composition API)
- Vite
- Pinia (State Management)
- TailwindCSS
- Axios

### Backend
- PHP 8.3
- Slim Framework 4
- Doctrine DBAL
- Firebase JWT
- Monolog

### Infrastruktur
- Docker & Docker Compose
- MySQL 8.0
- Redis
- Nginx

## Schnellstart

### Voraussetzungen
- Docker & Docker Compose
- Node.js 20+ (für Entwicklung)
- PHP 8.2+ (für lokale Entwicklung)

### Installation

1. Repository klonen:
```bash
git clone https://github.com/KyuubiDDragon/KyuubiSoft.git
cd KyuubiSoft
```

2. Umgebungsvariablen konfigurieren:
```bash
cp .env.example .env
# JWT_SECRET anpassen!
```

3. Docker-Container starten:
```bash
docker compose up -d
```

4. Datenbank-Migrationen ausführen:
```bash
docker compose exec backend php bin/migrate.php --seed
```

5. Frontend öffnen: http://localhost

### Entwicklung

**Backend:**
```bash
cd backend
composer install
cp .env.example .env
```

**Frontend:**
```bash
cd frontend
npm install
npm run dev
```

## Projektstruktur

```
KyuubiSoft/
├── backend/                 # PHP Backend
│   ├── config/             # Konfiguration
│   ├── database/           # Migrationen & Seeder
│   ├── public/             # Entry Point
│   ├── src/
│   │   ├── Core/           # Framework-Kern
│   │   ├── Modules/        # Feature-Module
│   │   └── Shared/         # Geteilte Komponenten
│   └── storage/            # Logs, Cache, Uploads
├── frontend/               # Vue.js Frontend
│   ├── src/
│   │   ├── assets/         # Styles, Images
│   │   ├── components/     # Wiederverwendbare Komponenten
│   │   ├── core/           # API, Guards, Plugins
│   │   ├── layouts/        # Layout-Komponenten
│   │   ├── modules/        # Feature-Module
│   │   ├── router/         # Vue Router
│   │   └── stores/         # Pinia Stores
│   └── public/
├── docker/                 # Docker-Konfiguration
│   ├── mysql/
│   └── nginx/
├── docs/                   # Dokumentation
└── docker-compose.yml
```

## API-Endpunkte

### Auth
- `POST /api/v1/auth/register` - Registrierung
- `POST /api/v1/auth/login` - Login
- `POST /api/v1/auth/refresh` - Token erneuern
- `POST /api/v1/auth/logout` - Logout
- `GET /api/v1/auth/me` - Aktueller Benutzer

### Listen
- `GET /api/v1/lists` - Alle Listen
- `POST /api/v1/lists` - Liste erstellen
- `GET /api/v1/lists/{id}` - Liste abrufen
- `PUT /api/v1/lists/{id}` - Liste aktualisieren
- `DELETE /api/v1/lists/{id}` - Liste löschen

### Dokumente
- `GET /api/v1/documents` - Alle Dokumente
- `POST /api/v1/documents` - Dokument erstellen
- `GET /api/v1/documents/{id}` - Dokument abrufen
- `PUT /api/v1/documents/{id}` - Dokument aktualisieren
- `DELETE /api/v1/documents/{id}` - Dokument löschen

## Sicherheit

- Passwörter werden mit Argon2id gehasht
- JWT Access Tokens (15 Min) + Refresh Tokens (7 Tage)
- Rate Limiting auf API-Endpunkten
- CORS konfiguriert
- SQL Injection Prevention durch Prepared Statements
- XSS Prevention durch Output Escaping

## Erweiterbarkeit

Das System ist modular aufgebaut. Neue Features können als Module hinzugefügt werden:

1. Backend: Neues Modul in `backend/src/Modules/`
2. Frontend: Neues Modul in `frontend/src/modules/`
3. Routen und Permissions registrieren
4. Datenbank-Migration erstellen

## Lizenz

Proprietär - Alle Rechte vorbehalten.
