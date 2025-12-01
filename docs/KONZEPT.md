# KyuubiSoft - Personal Dashboard & Tools Platform

## Inhaltsverzeichnis

1. [Projektübersicht](#1-projektübersicht)
2. [Architektur](#2-architektur)
3. [Technologie-Stack](#3-technologie-stack)
4. [Sicherheitskonzept](#4-sicherheitskonzept)
5. [Datenbankdesign](#5-datenbankdesign)
6. [Backend-Struktur](#6-backend-struktur)
7. [Frontend-Struktur](#7-frontend-struktur)
8. [Docker & Deployment](#8-docker--deployment)
9. [Feature-Katalog](#9-feature-katalog)
10. [Erweiterbarkeits-Konzept](#10-erweiterbarkeits-konzept)
11. [Entwicklungs-Roadmap](#11-entwicklungs-roadmap)

---

## 1. Projektübersicht

### Vision
Eine modulare, sichere und erweiterbare persönliche Plattform für Techniker, Gamer und Power-User. Das System dient als zentrales Dashboard für verschiedene Tools, Systemüberwachung und produktivitätssteigernde Funktionen.

### Kernprinzipien
- **Modularität**: Jedes Feature ist ein eigenständiges Modul
- **Sicherheit**: Zero-Trust-Architektur mit JWT & RBAC
- **Erweiterbarkeit**: Plugin-System für zukünftige Features
- **Self-Hosted**: Volle Kontrolle über Daten
- **Performance**: Schnelle Ladezeiten, effiziente API-Calls

---

## 2. Architektur

### 2.1 High-Level Architektur

```
┌─────────────────────────────────────────────────────────────────┐
│                         NGINX Reverse Proxy                      │
│                    (SSL Termination, Rate Limiting)              │
└─────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
                    ▼                               ▼
┌─────────────────────────────┐   ┌─────────────────────────────┐
│      Vue.js Frontend        │   │       PHP Backend API       │
│        (Port 80/443)        │   │        (Port 8080)          │
│  ┌───────────────────────┐  │   │  ┌───────────────────────┐  │
│  │   Vuex/Pinia Store    │  │   │  │    JWT Middleware     │  │
│  │   Vue Router          │  │   │  │    RBAC System        │  │
│  │   Component Library   │  │   │  │    Module Loader      │  │
│  └───────────────────────┘  │   │  └───────────────────────┘  │
└─────────────────────────────┘   └─────────────────────────────┘
                                              │
                    ┌─────────────────────────┼─────────────────────────┐
                    │                         │                         │
                    ▼                         ▼                         ▼
┌─────────────────────────┐   ┌─────────────────────────┐   ┌─────────────────────────┐
│        MySQL            │   │         Redis           │   │     File Storage        │
│    (Persistent Data)    │   │   (Cache & Sessions)    │   │   (Uploads, Backups)    │
└─────────────────────────┘   └─────────────────────────┘   └─────────────────────────┘
```

### 2.2 Microservice-ready Monolith

Das Backend folgt dem "Modular Monolith" Pattern:
- Einzelne Deployment-Einheit
- Interne Modul-Grenzen
- Kann später in Microservices aufgeteilt werden

---

## 3. Technologie-Stack

### Frontend
| Technologie | Version | Zweck |
|-------------|---------|-------|
| Vue.js | 3.4+ | Framework |
| Vite | 5.x | Build Tool |
| Pinia | 2.x | State Management |
| Vue Router | 4.x | Routing |
| TailwindCSS | 3.x | Styling |
| Axios | 1.x | HTTP Client |
| Monaco Editor | - | Code/Markdown Editor |
| Chart.js | 4.x | Datenvisualisierung |
| Socket.io Client | 4.x | Realtime Updates |

### Backend
| Technologie | Version | Zweck |
|-------------|---------|-------|
| PHP | 8.3+ | Sprache |
| Slim Framework | 4.x | Micro-Framework (Routing, DI) |
| Doctrine DBAL | 3.x | Database Abstraction |
| Firebase JWT | 6.x | Token Handling |
| Monolog | 3.x | Logging |
| PHPUnit | 10.x | Testing |
| PHP-DI | 7.x | Dependency Injection |

### Infrastruktur
| Technologie | Zweck |
|-------------|-------|
| Docker | Containerisierung |
| Docker Compose | Multi-Container Orchestrierung |
| Nginx | Reverse Proxy & Static Files |
| MySQL 8.0 | Datenbank |
| Redis | Caching & Sessions |
| Portainer | Container Management |
| GitHub Actions | CI/CD Pipeline |

---

## 4. Sicherheitskonzept

### 4.1 Authentifizierung (JWT)

```
┌─────────────────────────────────────────────────────────────┐
│                    JWT Token Flow                            │
└─────────────────────────────────────────────────────────────┘

1. Login Request
   ┌──────────┐         POST /api/auth/login          ┌──────────┐
   │  Client  │ ──────────────────────────────────▶   │  Server  │
   └──────────┘      {email, password, 2fa_code}      └──────────┘

2. Token Generation
   ┌──────────┐                                       ┌──────────┐
   │  Server  │ ─────── Validate Credentials ───────▶ │ Database │
   └──────────┘                                       └──────────┘
        │
        ▼
   Generate Access Token (15min) + Refresh Token (7 days)

3. Response
   ┌──────────┐         {access_token, refresh_token} ┌──────────┐
   │  Server  │ ◀────────────────────────────────────  │  Client  │
   └──────────┘         Stored in httpOnly cookies    └──────────┘

4. Authenticated Requests
   Authorization: Bearer <access_token>
```

#### JWT Payload Struktur
```json
{
  "sub": "user_uuid",
  "email": "user@example.com",
  "roles": ["admin", "user"],
  "permissions": ["system.read", "lists.write"],
  "iat": 1699999999,
  "exp": 1700000899,
  "jti": "unique_token_id"
}
```

### 4.2 Autorisierung (RBAC - Role-Based Access Control)

```
┌─────────────────────────────────────────────────────────────┐
│                    Hierarchisches RBAC                       │
└─────────────────────────────────────────────────────────────┘

                        ┌─────────────┐
                        │   OWNER     │  (Vollzugriff)
                        └──────┬──────┘
                               │
                        ┌──────┴──────┐
                        │   ADMIN     │  (Verwaltung)
                        └──────┬──────┘
                               │
              ┌────────────────┼────────────────┐
              │                │                │
       ┌──────┴──────┐  ┌──────┴──────┐  ┌──────┴──────┐
       │  MODERATOR  │  │   EDITOR    │  │   VIEWER    │
       └─────────────┘  └─────────────┘  └─────────────┘
```

#### Permission Matrix (Beispiel)
| Permission | Owner | Admin | Editor | Viewer |
|------------|-------|-------|--------|--------|
| system.manage | ✓ | ✗ | ✗ | ✗ |
| users.manage | ✓ | ✓ | ✗ | ✗ |
| modules.configure | ✓ | ✓ | ✗ | ✗ |
| content.create | ✓ | ✓ | ✓ | ✗ |
| content.read | ✓ | ✓ | ✓ | ✓ |
| content.update | ✓ | ✓ | ✓ | ✗ |
| content.delete | ✓ | ✓ | ✗ | ✗ |

### 4.3 Weitere Sicherheitsmaßnahmen

#### API Security
- **Rate Limiting**: 100 Requests/Minute pro User
- **CORS**: Strikte Origin-Policy
- **Input Validation**: Alle Eingaben werden validiert
- **SQL Injection Prevention**: Prepared Statements (Doctrine)
- **XSS Prevention**: Output Escaping + CSP Headers
- **CSRF Protection**: Token-basiert für State-Changes

#### Passwort-Policy
```php
// Passwort-Anforderungen
- Mindestlänge: 12 Zeichen
- Großbuchstaben: mindestens 1
- Kleinbuchstaben: mindestens 1
- Zahlen: mindestens 1
- Sonderzeichen: mindestens 1
- Hashing: Argon2id
```

#### Optional: 2FA (TOTP)
- Google Authenticator kompatibel
- Backup-Codes für Recovery
- Erzwingbar pro Rolle

### 4.4 Session & Token Management

```php
// Access Token: Kurzlebig, stateless
$accessTokenLifetime = 900; // 15 Minuten

// Refresh Token: Langlebig, in DB gespeichert
$refreshTokenLifetime = 604800; // 7 Tage

// Token Rotation bei jedem Refresh
// Alte Tokens werden invalidiert
```

---

## 5. Datenbankdesign

### 5.1 ERD (Entity Relationship Diagram)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              CORE ENTITIES                                   │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│      users       │     │      roles       │     │   permissions    │
├──────────────────┤     ├──────────────────┤     ├──────────────────┤
│ id (UUID) PK     │     │ id (INT) PK      │     │ id (INT) PK      │
│ email            │     │ name             │     │ name             │
│ password_hash    │     │ description      │     │ description      │
│ username         │     │ is_system        │     │ module           │
│ avatar_url       │     │ hierarchy_level  │     │ created_at       │
│ is_active        │     │ created_at       │     └────────┬─────────┘
│ is_verified      │     │ updated_at       │              │
│ two_factor_secret│     └────────┬─────────┘              │
│ last_login_at    │              │                        │
│ created_at       │              │                        │
│ updated_at       │     ┌────────┴─────────┐     ┌────────┴─────────┐
└────────┬─────────┘     │   user_roles     │     │role_permissions  │
         │               ├──────────────────┤     ├──────────────────┤
         │               │ user_id FK       │     │ role_id FK       │
         │               │ role_id FK       │     │ permission_id FK │
         │               │ assigned_at      │     │ granted_at       │
         │               │ assigned_by      │     └──────────────────┘
         │               └──────────────────┘
         │
         │     ┌──────────────────┐     ┌──────────────────┐
         │     │  refresh_tokens  │     │   audit_logs     │
         │     ├──────────────────┤     ├──────────────────┤
         └────▶│ id (UUID) PK     │     │ id (BIGINT) PK   │
               │ user_id FK       │     │ user_id FK       │
               │ token_hash       │     │ action           │
               │ expires_at       │     │ entity_type      │
               │ revoked_at       │     │ entity_id        │
               │ user_agent       │     │ old_values       │
               │ ip_address       │     │ new_values       │
               │ created_at       │     │ ip_address       │
               └──────────────────┘     │ user_agent       │
                                        │ created_at       │
                                        └──────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                             MODULE: SETTINGS                                 │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────────┐     ┌──────────────────┐
│  system_settings │     │  user_settings   │
├──────────────────┤     ├──────────────────┤
│ key (VARCHAR) PK │     │ user_id FK       │
│ value (JSON)     │     │ key (VARCHAR)    │
│ type             │     │ value (JSON)     │
│ description      │     │ created_at       │
│ is_public        │     │ updated_at       │
│ updated_at       │     └──────────────────┘
│ updated_by       │
└──────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                              MODULE: LISTS                                   │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│      lists       │     │    list_items    │     │   list_shares    │
├──────────────────┤     ├──────────────────┤     ├──────────────────┤
│ id (UUID) PK     │     │ id (UUID) PK     │     │ list_id FK       │
│ user_id FK       │     │ list_id FK       │     │ user_id FK       │
│ title            │     │ content          │     │ permission       │
│ description      │     │ is_completed     │     │ created_at       │
│ type (enum)      │     │ position         │     └──────────────────┘
│ color            │     │ due_date         │
│ icon             │     │ priority         │
│ is_archived      │     │ metadata (JSON)  │
│ created_at       │     │ created_at       │
│ updated_at       │     │ updated_at       │
└──────────────────┘     └──────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                             MODULE: DOCUMENTS                                │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│    documents     │     │document_versions │     │    doc_shares    │
├──────────────────┤     ├──────────────────┤     ├──────────────────┤
│ id (UUID) PK     │     │ id (UUID) PK     │     │ document_id FK   │
│ user_id FK       │     │ document_id FK   │     │ user_id FK       │
│ title            │     │ content          │     │ permission       │
│ content          │     │ version_number   │     │ created_at       │
│ format (enum)    │     │ created_by FK    │     └──────────────────┘
│ folder_id FK     │     │ created_at       │
│ is_archived      │     │ change_summary   │
│ created_at       │     └──────────────────┘
│ updated_at       │
└──────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                            MODULE: MONITORING                                │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│ monitored_hosts  │     │    metrics       │     │     alerts       │
├──────────────────┤     ├──────────────────┤     ├──────────────────┤
│ id (UUID) PK     │     │ id (BIGINT) PK   │     │ id (UUID) PK     │
│ user_id FK       │     │ host_id FK       │     │ host_id FK       │
│ name             │     │ metric_type      │     │ rule_id FK       │
│ hostname/ip      │     │ value            │     │ severity         │
│ type (enum)      │     │ recorded_at      │     │ message          │
│ credentials(enc) │     └──────────────────┘     │ acknowledged_at  │
│ check_interval   │                              │ resolved_at      │
│ is_active        │     ┌──────────────────┐     │ created_at       │
│ last_check_at    │     │   alert_rules    │     └──────────────────┘
│ status           │     ├──────────────────┤
│ created_at       │     │ id (UUID) PK     │
│ updated_at       │     │ host_id FK       │
└──────────────────┘     │ metric_type      │
                         │ condition        │
                         │ threshold        │
                         │ notification_ch  │
                         │ is_active        │
                         │ created_at       │
                         └──────────────────┘
```

### 5.2 Migrations-Strategie

```
/backend/database/migrations/
├── 2024_01_001_create_users_table.php
├── 2024_01_002_create_roles_table.php
├── 2024_01_003_create_permissions_table.php
├── 2024_01_004_create_role_permissions_table.php
├── 2024_01_005_create_user_roles_table.php
├── 2024_01_006_create_refresh_tokens_table.php
├── 2024_01_007_create_audit_logs_table.php
├── 2024_01_008_create_settings_tables.php
└── modules/
    ├── lists/
    │   └── 2024_02_001_create_lists_tables.php
    ├── documents/
    │   └── 2024_02_002_create_documents_tables.php
    └── monitoring/
        └── 2024_02_003_create_monitoring_tables.php
```

---

## 6. Backend-Struktur

### 6.1 Verzeichnisstruktur

```
/backend/
├── public/
│   └── index.php                    # Entry Point
├── src/
│   ├── Core/
│   │   ├── Application.php          # App Bootstrap
│   │   ├── Container.php            # DI Container Setup
│   │   ├── Router.php               # Route Registration
│   │   ├── Middleware/
│   │   │   ├── AuthMiddleware.php
│   │   │   ├── CorsMiddleware.php
│   │   │   ├── RateLimitMiddleware.php
│   │   │   └── ValidationMiddleware.php
│   │   ├── Security/
│   │   │   ├── JwtManager.php
│   │   │   ├── PasswordHasher.php
│   │   │   ├── RbacManager.php
│   │   │   └── TwoFactorAuth.php
│   │   ├── Database/
│   │   │   ├── Connection.php
│   │   │   ├── Migration.php
│   │   │   └── QueryBuilder.php
│   │   ├── Http/
│   │   │   ├── Request.php
│   │   │   ├── Response.php
│   │   │   └── JsonResponse.php
│   │   └── Exceptions/
│   │       ├── AuthException.php
│   │       ├── ValidationException.php
│   │       └── NotFoundException.php
│   │
│   ├── Modules/
│   │   ├── Auth/
│   │   │   ├── Controllers/
│   │   │   │   └── AuthController.php
│   │   │   ├── Services/
│   │   │   │   └── AuthService.php
│   │   │   ├── Repositories/
│   │   │   │   └── UserRepository.php
│   │   │   ├── DTOs/
│   │   │   │   ├── LoginRequest.php
│   │   │   │   └── RegisterRequest.php
│   │   │   └── routes.php
│   │   │
│   │   ├── Users/
│   │   │   ├── Controllers/
│   │   │   ├── Services/
│   │   │   ├── Repositories/
│   │   │   └── routes.php
│   │   │
│   │   ├── Lists/
│   │   │   ├── Controllers/
│   │   │   │   └── ListController.php
│   │   │   ├── Services/
│   │   │   │   └── ListService.php
│   │   │   ├── Repositories/
│   │   │   │   └── ListRepository.php
│   │   │   ├── Models/
│   │   │   │   ├── TodoList.php
│   │   │   │   └── ListItem.php
│   │   │   └── routes.php
│   │   │
│   │   ├── Documents/
│   │   │   ├── Controllers/
│   │   │   ├── Services/
│   │   │   └── routes.php
│   │   │
│   │   ├── Monitoring/
│   │   │   ├── Controllers/
│   │   │   ├── Services/
│   │   │   ├── Jobs/           # Background Tasks
│   │   │   └── routes.php
│   │   │
│   │   └── Settings/
│   │       ├── Controllers/
│   │       ├── Services/
│   │       └── routes.php
│   │
│   └── Shared/
│       ├── Traits/
│       │   ├── HasUuid.php
│       │   └── Auditable.php
│       ├── Services/
│       │   ├── CacheService.php
│       │   └── NotificationService.php
│       └── ValueObjects/
│           ├── Email.php
│           └── Password.php
│
├── config/
│   ├── app.php
│   ├── database.php
│   ├── jwt.php
│   ├── cors.php
│   └── modules.php
│
├── database/
│   ├── migrations/
│   └── seeders/
│
├── storage/
│   ├── logs/
│   ├── cache/
│   └── uploads/
│
├── tests/
│   ├── Unit/
│   └── Integration/
│
├── composer.json
├── phpunit.xml
└── .env.example
```

### 6.2 API-Endpunkte (RESTful)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              AUTH MODULE                                     │
└─────────────────────────────────────────────────────────────────────────────┘

POST   /api/v1/auth/register         # Registrierung
POST   /api/v1/auth/login            # Login
POST   /api/v1/auth/refresh          # Token Refresh
POST   /api/v1/auth/logout           # Logout (Token invalidieren)
POST   /api/v1/auth/forgot-password  # Passwort vergessen
POST   /api/v1/auth/reset-password   # Passwort zurücksetzen
POST   /api/v1/auth/verify-email     # E-Mail verifizieren
POST   /api/v1/auth/2fa/enable       # 2FA aktivieren
POST   /api/v1/auth/2fa/verify       # 2FA verifizieren
DELETE /api/v1/auth/2fa/disable      # 2FA deaktivieren

┌─────────────────────────────────────────────────────────────────────────────┐
│                              USERS MODULE                                    │
└─────────────────────────────────────────────────────────────────────────────┘

GET    /api/v1/users                 # Liste aller User (Admin)
GET    /api/v1/users/{id}            # User Details
PUT    /api/v1/users/{id}            # User aktualisieren
DELETE /api/v1/users/{id}            # User löschen
GET    /api/v1/users/me              # Eigenes Profil
PUT    /api/v1/users/me              # Eigenes Profil aktualisieren
PUT    /api/v1/users/me/password     # Passwort ändern
POST   /api/v1/users/me/avatar       # Avatar hochladen

┌─────────────────────────────────────────────────────────────────────────────┐
│                              LISTS MODULE                                    │
└─────────────────────────────────────────────────────────────────────────────┘

GET    /api/v1/lists                 # Alle Listen
POST   /api/v1/lists                 # Liste erstellen
GET    /api/v1/lists/{id}            # Liste abrufen
PUT    /api/v1/lists/{id}            # Liste aktualisieren
DELETE /api/v1/lists/{id}            # Liste löschen
POST   /api/v1/lists/{id}/items      # Item hinzufügen
PUT    /api/v1/lists/{id}/items/{itemId}    # Item aktualisieren
DELETE /api/v1/lists/{id}/items/{itemId}    # Item löschen
PUT    /api/v1/lists/{id}/items/reorder     # Items umsortieren
POST   /api/v1/lists/{id}/share      # Liste teilen

┌─────────────────────────────────────────────────────────────────────────────┐
│                            DOCUMENTS MODULE                                  │
└─────────────────────────────────────────────────────────────────────────────┘

GET    /api/v1/documents             # Alle Dokumente
POST   /api/v1/documents             # Dokument erstellen
GET    /api/v1/documents/{id}        # Dokument abrufen
PUT    /api/v1/documents/{id}        # Dokument aktualisieren
DELETE /api/v1/documents/{id}        # Dokument löschen
GET    /api/v1/documents/{id}/versions      # Versionshistorie
GET    /api/v1/documents/{id}/versions/{v}  # Spezifische Version
POST   /api/v1/documents/{id}/share         # Dokument teilen

┌─────────────────────────────────────────────────────────────────────────────┐
│                           MONITORING MODULE                                  │
└─────────────────────────────────────────────────────────────────────────────┘

GET    /api/v1/monitoring/hosts      # Alle überwachten Hosts
POST   /api/v1/monitoring/hosts      # Host hinzufügen
GET    /api/v1/monitoring/hosts/{id} # Host Details
PUT    /api/v1/monitoring/hosts/{id} # Host aktualisieren
DELETE /api/v1/monitoring/hosts/{id} # Host löschen
GET    /api/v1/monitoring/hosts/{id}/metrics     # Metriken abrufen
GET    /api/v1/monitoring/hosts/{id}/alerts      # Alerts abrufen
POST   /api/v1/monitoring/hosts/{id}/rules       # Alert-Regel erstellen
GET    /api/v1/monitoring/dashboard  # Dashboard Übersicht

┌─────────────────────────────────────────────────────────────────────────────┐
│                            SETTINGS MODULE                                   │
└─────────────────────────────────────────────────────────────────────────────┘

GET    /api/v1/settings/system       # System-Einstellungen (Admin)
PUT    /api/v1/settings/system       # System-Einstellungen ändern
GET    /api/v1/settings/user         # User-Einstellungen
PUT    /api/v1/settings/user         # User-Einstellungen ändern
```

### 6.3 Modul-Registrierung

```php
// config/modules.php
return [
    'modules' => [
        'auth' => [
            'enabled' => true,
            'path' => 'Modules/Auth',
        ],
        'users' => [
            'enabled' => true,
            'path' => 'Modules/Users',
            'permissions' => ['users.read', 'users.write', 'users.delete'],
        ],
        'lists' => [
            'enabled' => true,
            'path' => 'Modules/Lists',
            'permissions' => ['lists.read', 'lists.write', 'lists.delete', 'lists.share'],
        ],
        'documents' => [
            'enabled' => true,
            'path' => 'Modules/Documents',
            'permissions' => ['documents.read', 'documents.write', 'documents.delete'],
        ],
        'monitoring' => [
            'enabled' => true,
            'path' => 'Modules/Monitoring',
            'permissions' => ['monitoring.read', 'monitoring.configure'],
        ],
    ],
];
```

---

## 7. Frontend-Struktur

### 7.1 Verzeichnisstruktur

```
/frontend/
├── public/
│   ├── index.html
│   └── favicon.ico
├── src/
│   ├── main.js                      # Entry Point
│   ├── App.vue                      # Root Component
│   │
│   ├── core/
│   │   ├── api/
│   │   │   ├── axios.js             # Axios Instance mit Interceptors
│   │   │   ├── auth.api.js
│   │   │   └── base.api.js
│   │   ├── composables/
│   │   │   ├── useAuth.js
│   │   │   ├── usePermissions.js
│   │   │   └── useNotification.js
│   │   ├── guards/
│   │   │   ├── authGuard.js
│   │   │   └── permissionGuard.js
│   │   └── plugins/
│   │       └── index.js
│   │
│   ├── stores/
│   │   ├── index.js                 # Pinia Setup
│   │   ├── auth.store.js
│   │   ├── user.store.js
│   │   └── ui.store.js
│   │
│   ├── router/
│   │   ├── index.js
│   │   └── routes/
│   │       ├── auth.routes.js
│   │       ├── dashboard.routes.js
│   │       ├── lists.routes.js
│   │       └── monitoring.routes.js
│   │
│   ├── layouts/
│   │   ├── DefaultLayout.vue
│   │   ├── AuthLayout.vue
│   │   └── components/
│   │       ├── Sidebar.vue
│   │       ├── Header.vue
│   │       └── Footer.vue
│   │
│   ├── components/
│   │   ├── common/
│   │   │   ├── Button.vue
│   │   │   ├── Input.vue
│   │   │   ├── Modal.vue
│   │   │   ├── Card.vue
│   │   │   ├── Table.vue
│   │   │   ├── Dropdown.vue
│   │   │   └── Loading.vue
│   │   ├── forms/
│   │   │   ├── FormField.vue
│   │   │   └── FormValidation.vue
│   │   └── icons/
│   │       └── IconSet.vue
│   │
│   ├── modules/
│   │   ├── auth/
│   │   │   ├── views/
│   │   │   │   ├── LoginView.vue
│   │   │   │   ├── RegisterView.vue
│   │   │   │   └── ForgotPasswordView.vue
│   │   │   └── components/
│   │   │       └── TwoFactorSetup.vue
│   │   │
│   │   ├── dashboard/
│   │   │   ├── views/
│   │   │   │   └── DashboardView.vue
│   │   │   └── components/
│   │   │       ├── StatCard.vue
│   │   │       └── ActivityFeed.vue
│   │   │
│   │   ├── lists/
│   │   │   ├── views/
│   │   │   │   ├── ListsView.vue
│   │   │   │   └── ListDetailView.vue
│   │   │   ├── components/
│   │   │   │   ├── ListCard.vue
│   │   │   │   ├── ListItem.vue
│   │   │   │   └── ListEditor.vue
│   │   │   └── stores/
│   │   │       └── lists.store.js
│   │   │
│   │   ├── documents/
│   │   │   ├── views/
│   │   │   │   └── DocumentsView.vue
│   │   │   └── components/
│   │   │       ├── MarkdownEditor.vue
│   │   │       └── DocumentTree.vue
│   │   │
│   │   ├── monitoring/
│   │   │   ├── views/
│   │   │   │   ├── MonitoringView.vue
│   │   │   │   └── HostDetailView.vue
│   │   │   └── components/
│   │   │       ├── HostCard.vue
│   │   │       ├── MetricsChart.vue
│   │   │       └── AlertList.vue
│   │   │
│   │   └── settings/
│   │       ├── views/
│   │       │   └── SettingsView.vue
│   │       └── components/
│   │           ├── ProfileSettings.vue
│   │           ├── SecuritySettings.vue
│   │           └── AppearanceSettings.vue
│   │
│   ├── assets/
│   │   ├── styles/
│   │   │   ├── main.css
│   │   │   └── tailwind.css
│   │   └── images/
│   │
│   └── utils/
│       ├── validators.js
│       ├── formatters.js
│       └── constants.js
│
├── package.json
├── vite.config.js
├── tailwind.config.js
├── postcss.config.js
└── .env.example
```

### 7.2 State Management (Pinia)

```javascript
// stores/auth.store.js
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    permissions: [],
    isAuthenticated: false,
    isLoading: false,
  }),

  getters: {
    hasPermission: (state) => (permission) => {
      return state.permissions.includes(permission);
    },
    hasRole: (state) => (role) => {
      return state.user?.roles?.includes(role);
    },
  },

  actions: {
    async login(credentials) { /* ... */ },
    async logout() { /* ... */ },
    async refreshToken() { /* ... */ },
    async fetchUser() { /* ... */ },
  },
});
```

### 7.3 Route Guards

```javascript
// core/guards/authGuard.js
export const authGuard = async (to, from, next) => {
  const authStore = useAuthStore();

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } });
  }

  if (to.meta.permission && !authStore.hasPermission(to.meta.permission)) {
    return next({ name: 'forbidden' });
  }

  next();
};
```

---

## 8. Docker & Deployment

### 8.1 Docker-Compose Struktur

```yaml
# docker-compose.yml
version: '3.8'

services:
  # ===================
  # NGINX Reverse Proxy
  # ===================
  nginx:
    image: nginx:alpine
    container_name: kyuubisoft_nginx
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./docker/nginx/conf.d:/etc/nginx/conf.d:ro
      - ./docker/nginx/ssl:/etc/nginx/ssl:ro
      - ./frontend/dist:/var/www/frontend:ro
    depends_on:
      - frontend
      - backend
    networks:
      - kyuubisoft_network
    restart: unless-stopped

  # ===================
  # Vue.js Frontend
  # ===================
  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
      args:
        - VITE_API_URL=${VITE_API_URL}
    container_name: kyuubisoft_frontend
    volumes:
      - frontend_dist:/app/dist
    environment:
      - NODE_ENV=production
    networks:
      - kyuubisoft_network
    restart: unless-stopped

  # ===================
  # PHP Backend
  # ===================
  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile
    container_name: kyuubisoft_backend
    volumes:
      - ./backend:/var/www/html
      - ./backend/storage:/var/www/html/storage
    environment:
      - APP_ENV=${APP_ENV:-production}
      - APP_DEBUG=${APP_DEBUG:-false}
      - DB_HOST=mysql
      - DB_PORT=3306
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - JWT_SECRET=${JWT_SECRET}
      - REDIS_HOST=redis
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_started
    networks:
      - kyuubisoft_network
    restart: unless-stopped

  # ===================
  # MySQL Database
  # ===================
  mysql:
    image: mysql:8.0
    container_name: kyuubisoft_mysql
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/init:/docker-entrypoint-initdb.d:ro
    environment:
      - MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
      - MYSQL_DATABASE=${DB_DATABASE}
      - MYSQL_USER=${DB_USERNAME}
      - MYSQL_PASSWORD=${DB_PASSWORD}
    command: --default-authentication-plugin=mysql_native_password
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - kyuubisoft_network
    restart: unless-stopped

  # ===================
  # Redis Cache
  # ===================
  redis:
    image: redis:7-alpine
    container_name: kyuubisoft_redis
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    command: redis-server --appendonly yes
    networks:
      - kyuubisoft_network
    restart: unless-stopped

  # ===================
  # Background Worker (Optional)
  # ===================
  worker:
    build:
      context: ./backend
      dockerfile: Dockerfile.worker
    container_name: kyuubisoft_worker
    volumes:
      - ./backend:/var/www/html
    environment:
      - APP_ENV=${APP_ENV:-production}
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on:
      - backend
      - redis
    networks:
      - kyuubisoft_network
    restart: unless-stopped

networks:
  kyuubisoft_network:
    driver: bridge

volumes:
  mysql_data:
  redis_data:
  frontend_dist:
```

### 8.2 Dockerfiles

#### Backend Dockerfile
```dockerfile
# backend/Dockerfile
FROM php:8.3-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    zip \
    unzip \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Copy configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

EXPOSE 9000

CMD ["php-fpm"]
```

#### Frontend Dockerfile
```dockerfile
# frontend/Dockerfile
FROM node:20-alpine as build

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci

# Copy source
COPY . .

# Build arguments
ARG VITE_API_URL
ENV VITE_API_URL=$VITE_API_URL

# Build
RUN npm run build

# Production stage
FROM nginx:alpine

COPY --from=build /app/dist /usr/share/nginx/html
COPY docker/nginx/frontend.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
```

### 8.3 GitHub Actions CI/CD

```yaml
# .github/workflows/deploy.yml
name: Build and Deploy

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install Backend Dependencies
        run: cd backend && composer install

      - name: Run Backend Tests
        run: cd backend && ./vendor/bin/phpunit

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Install Frontend Dependencies
        run: cd frontend && npm ci

      - name: Run Frontend Tests
        run: cd frontend && npm run test

      - name: Build Frontend
        run: cd frontend && npm run build

  build-and-push:
    needs: test
    runs-on: ubuntu-latest
    if: github.event_name == 'push' && github.ref == 'refs/heads/main'

    permissions:
      contents: read
      packages: write

    steps:
      - uses: actions/checkout@v4

      - name: Log in to Container Registry
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Build and Push Backend Image
        uses: docker/build-push-action@v5
        with:
          context: ./backend
          push: true
          tags: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}/backend:latest

      - name: Build and Push Frontend Image
        uses: docker/build-push-action@v5
        with:
          context: ./frontend
          push: true
          tags: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}/frontend:latest
          build-args: |
            VITE_API_URL=${{ secrets.VITE_API_URL }}

  deploy:
    needs: build-and-push
    runs-on: ubuntu-latest

    steps:
      - name: Deploy to Server
        uses: appleboy/ssh-action@v1.0.0
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SERVER_SSH_KEY }}
          script: |
            cd /opt/kyuubisoft
            docker compose pull
            docker compose up -d --remove-orphans
            docker system prune -f
```

### 8.4 Portainer Integration

```yaml
# portainer-stack.yml (für Portainer Stacks)
version: '3.8'

services:
  nginx:
    image: ghcr.io/kyuubidragon/kyuubisoft/nginx:latest
    # ... rest of config

  backend:
    image: ghcr.io/kyuubidragon/kyuubisoft/backend:latest
    # ... rest of config

  frontend:
    image: ghcr.io/kyuubidragon/kyuubisoft/frontend:latest
    # ... rest of config
```

**Portainer Webhook für Auto-Deploy:**
```
POST https://portainer.yourdomain.com/api/webhooks/{webhook_id}
```

---

## 9. Feature-Katalog

### 9.1 Kern-Features (Phase 1)

| Feature | Beschreibung | Priorität |
|---------|--------------|-----------|
| **Benutzer-System** | Registrierung, Login, Profil, 2FA | Kritisch |
| **Dashboard** | Übersicht aller Module, Widgets | Kritisch |
| **Listen-Tool** | Todo-Listen, Einkaufslisten, etc. | Hoch |
| **Markdown Editor** | Dokumente erstellen/bearbeiten | Hoch |
| **Einstellungen** | System- und User-Einstellungen | Hoch |

### 9.2 Erweiterte Features (Phase 2)

| Feature | Beschreibung | Priorität |
|---------|--------------|-----------|
| **System-Monitoring** | Server-Überwachung (CPU, RAM, Disk) | Mittel |
| **Bookmark Manager** | Links organisieren mit Tags | Mittel |
| **Notizen** | Schnelle Notizen mit Kategorien | Mittel |
| **Kalender** | Termine und Erinnerungen | Mittel |
| **Datei-Manager** | Upload, Download, Organisation | Mittel |

### 9.3 Power-User Features (Phase 3)

| Feature | Beschreibung | Priorität |
|---------|--------------|-----------|
| **Game Server Monitor** | Minecraft, ARK, etc. Status | Optional |
| **Docker Dashboard** | Container-Übersicht | Optional |
| **API Tester** | REST API testen (wie Postman) | Optional |
| **Code Snippets** | Code-Schnipsel speichern | Optional |
| **RSS Reader** | News-Feeds aggregieren | Optional |
| **Password Manager** | Sichere Passwort-Speicherung | Optional |
| **Home Automation** | Smart Home Integration | Optional |
| **Expense Tracker** | Ausgaben verfolgen | Optional |

### 9.4 Detaillierte Feature-Spezifikationen

#### Listen-Tool
```
Features:
├── Verschiedene Listen-Typen
│   ├── Todo-Liste (mit Checkboxen)
│   ├── Einkaufsliste
│   ├── Projekt-Liste
│   └── Benutzerdefiniert
├── Funktionen
│   ├── Drag & Drop Sortierung
│   ├── Fälligkeitsdatum
│   ├── Prioritäten (Hoch/Mittel/Niedrig)
│   ├── Tags/Labels
│   ├── Suche & Filter
│   └── Listen teilen
└── Views
    ├── Listen-Übersicht
    ├── Kanban-Board
    └── Kalender-Ansicht
```

#### Markdown Editor
```
Features:
├── Editor
│   ├── Split-View (Edit/Preview)
│   ├── Syntax Highlighting
│   ├── Auto-Save
│   └── Keyboard Shortcuts
├── Erweiterungen
│   ├── Mermaid Diagramme
│   ├── Math (KaTeX)
│   ├── Code Blocks mit Highlighting
│   └── Bild-Upload
├── Organisation
│   ├── Ordner-Struktur
│   ├── Tags
│   ├── Suche
│   └── Versionshistorie
└── Export
    ├── PDF
    ├── HTML
    └── Plain Text
```

#### System-Monitoring
```
Features:
├── Host-Verwaltung
│   ├── SSH-basiertes Monitoring
│   ├── Agent-basiert (optional)
│   └── SNMP Support
├── Metriken
│   ├── CPU Auslastung
│   ├── RAM Nutzung
│   ├── Disk Space
│   ├── Netzwerk I/O
│   ├── Prozesse
│   └── Custom Checks
├── Visualisierung
│   ├── Live-Charts
│   ├── Historische Daten
│   └── Dashboards
└── Alerting
    ├── E-Mail Benachrichtigung
    ├── Webhook/Discord
    └── Push-Notification
```

---

## 10. Erweiterbarkeits-Konzept

### 10.1 Plugin-Architektur

```
┌─────────────────────────────────────────────────────────────┐
│                     Plugin System                            │
└─────────────────────────────────────────────────────────────┘

                    ┌─────────────────┐
                    │   Plugin Core   │
                    │  (Event System) │
                    └────────┬────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
        ▼                    ▼                    ▼
┌───────────────┐   ┌───────────────┐   ┌───────────────┐
│  Plugin API   │   │   Hooks &     │   │   UI Slots    │
│  (Backend)    │   │   Events      │   │  (Frontend)   │
└───────────────┘   └───────────────┘   └───────────────┘
```

### 10.2 Modul-Interface

```php
// src/Core/Contracts/ModuleInterface.php
interface ModuleInterface
{
    public function getName(): string;
    public function getVersion(): string;
    public function getDependencies(): array;
    public function getPermissions(): array;
    public function register(Application $app): void;
    public function boot(): void;
    public function getRoutes(): array;
    public function getMigrations(): array;
}
```

### 10.3 Event-System

```php
// Verfügbare Events
$events = [
    'user.registered',
    'user.login',
    'user.logout',
    'list.created',
    'list.updated',
    'document.saved',
    'monitoring.alert',
    // ...
];

// Event Listener registrieren
$eventDispatcher->listen('user.login', function($user) {
    // Log, notify, etc.
});
```

### 10.4 Frontend-Erweiterungspunkte

```javascript
// Module können sich in UI-Slots einklinken
const slots = {
  'dashboard.widgets': [],      // Dashboard Widgets
  'sidebar.menu': [],           // Sidebar Menü-Items
  'header.actions': [],         // Header Actions
  'settings.tabs': [],          // Settings Tabs
};

// Modul registriert sich
registerSlot('dashboard.widgets', {
  component: MyWidget,
  order: 10,
});
```

---

## 11. Entwicklungs-Roadmap

### Phase 1: Foundation (Basis-Setup)
- [ ] Projekt-Struktur erstellen
- [ ] Docker-Umgebung aufsetzen
- [ ] Backend Basis (Slim Framework, DI, Routing)
- [ ] Datenbank-Migrationen (Core Tables)
- [ ] JWT Authentication
- [ ] RBAC System
- [ ] Frontend Basis (Vue 3, Pinia, Router)
- [ ] Login/Register UI
- [ ] Dashboard Layout

### Phase 2: Core Features
- [ ] Listen-Modul (Backend + Frontend)
- [ ] Markdown Editor
- [ ] User Profile & Settings
- [ ] Audit Logging

### Phase 3: Advanced Features
- [ ] System-Monitoring
- [ ] Alerting System
- [ ] WebSocket Integration (Live Updates)
- [ ] File Upload System

### Phase 4: Polish & Extend
- [ ] Dark Mode
- [ ] PWA Support
- [ ] Mobile Responsive
- [ ] Weitere Module nach Bedarf

---

## Anhang

### A. Sicherheits-Checkliste

- [ ] HTTPS überall
- [ ] JWT mit kurzer Laufzeit
- [ ] Refresh Token Rotation
- [ ] Rate Limiting aktiviert
- [ ] CORS konfiguriert
- [ ] CSP Headers gesetzt
- [ ] SQL Injection verhindert
- [ ] XSS verhindert
- [ ] Passwords mit Argon2id gehasht
- [ ] Secrets nicht im Code
- [ ] Audit Logging aktiv

### B. Empfohlene VS Code Extensions

- Vue - Official
- Tailwind CSS IntelliSense
- PHP Intelephense
- Docker
- GitLens
- Thunder Client (API Testing)

### C. Nützliche Commands

```bash
# Development starten
docker compose up -d

# Backend Migrations
docker compose exec backend php migrate

# Frontend Dev Server
cd frontend && npm run dev

# Tests ausführen
docker compose exec backend ./vendor/bin/phpunit
cd frontend && npm run test

# Logs anzeigen
docker compose logs -f backend
```
