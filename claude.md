# KyuubiSoft - Claude AI Assistant Guidelines

Diese Dokumentation ist die zentrale Wissensbasis für AI-Assistenten, die an diesem Projekt arbeiten.

---

## Projekt-Übersicht

KyuubiSoft ist eine self-hosted Produktivitäts- und Entwicklungsplattform mit umfangreichen Features:

### Core Features
- **Listen** - Todo-Listen mit Sharing
- **Dokumente** - Rich-Text-Dokumente mit kollaborativem Editing
- **Snippets** - Code-Snippet-Verwaltung
- **Bookmarks** - URL-Bookmark-Manager

### KyuubiCloud
- **Cloud Storage** - Datei-Upload mit Sharing
- **Checklisten** - Shared Checklisten mit öffentlichem Zugang

### Projektmanagement
- **Kanban** - Kanban-Boards mit Tags, Checklisten, Kommentaren
- **Projekte** - Projekt-Verwaltung mit Verlinkungen
- **Kalender** - Events + externe iCal-Kalender
- **Zeiterfassung** - Time-Tracking mit Reporting

### Development & Tools
- **Verbindungen** - SSH-Verbindungen mit Terminal
- **Server** - Server-Monitoring (CPU, RAM, Prozesse, Services)
- **Docker** - Container-Management + Portainer-Integration
- **Uptime Monitor** - Multi-Protokoll Service-Monitoring
- **Toolbox** - Netzwerk-Tools (Ping, DNS, SSL, WHOIS, etc.)
- **Webhooks** - Webhook-Management

### Business & Support
- **Tickets** - Support-Ticket-System mit öffentlichem Zugang
- **Rechnungen** - Invoice-Management

---

## Architektur

### Tech Stack
| Komponente | Technologie |
|------------|-------------|
| **Backend** | PHP 8.2+ mit Slim Framework 4 |
| **Frontend** | Vue.js 3 mit Composition API |
| **State** | Pinia Stores |
| **Styling** | Tailwind CSS |
| **Database** | MySQL/MariaDB |
| **Cache** | Redis |
| **Auth** | JWT (Access + Refresh Tokens) |

### Wichtige Verzeichnisse

```
/home/user/KyuubiSoft/
├── backend/
│   ├── src/
│   │   ├── Core/               # Framework-Kern
│   │   │   ├── Middleware/     # Auth, CORS, Features, Permissions
│   │   │   ├── Services/       # FeatureService, CacheService
│   │   │   └── Security/       # JwtManager, RbacManager
│   │   └── Modules/            # Feature-Module
│   │       └── {Module}/
│   │           ├── Controllers/
│   │           ├── Services/
│   │           └── Repositories/
│   ├── database/migrations/    # SQL-Migrationen
│   └── public/index.php        # Entry Point
│
├── frontend/
│   ├── src/
│   │   ├── core/api/axios.js   # API-Client mit Token-Refresh
│   │   ├── stores/             # Pinia Stores
│   │   │   ├── auth.js         # Authentifizierung
│   │   │   ├── features.js     # Feature-Flags
│   │   │   ├── ui.js           # UI-State
│   │   │   └── project.js      # Projekt-Filter
│   │   ├── layouts/
│   │   │   ├── DefaultLayout.vue   # Haupt-Layout (mit Sidebar)
│   │   │   ├── AuthLayout.vue      # Login/Register
│   │   │   └── PublicLayout.vue    # Öffentliche Seiten
│   │   ├── modules/            # Feature-Module
│   │   │   └── {module}/views/
│   │   └── router/index.js     # Vue Router
│   └── package.json
│
└── claude.md                   # Diese Datei
```

---

## WICHTIG: Fragen bei Unklarheiten

### Bei JEDER neuen Feature-Anfrage diese Fragen klären:

**1. Zugriffskontrolle**
- Soll die Seite/das Feature öffentlich (ohne Login) oder geschützt sein?
- Welche Benutzerrollen sollen Zugriff haben? (owner, admin, user)
- Soll das Feature per Feature-Flag steuerbar sein?
- Gibt es Sub-Features mit unterschiedlichen Berechtigungen?

**2. Datenmodell**
- Welche Daten werden gespeichert?
- Gibt es Beziehungen zu anderen Entitäten (User, Projekt, etc.)?
- Soll Sharing zwischen Usern möglich sein?
- Werden Soft-Deletes benötigt?

**3. UI/UX**
- Wo soll das Feature in der Navigation erscheinen?
- Soll es in eine bestehende Gruppe oder als eigenständiger Eintrag?
- Welche Views werden benötigt (Liste, Detail, Modal)?

**4. API-Design**
- Welche CRUD-Operationen werden benötigt?
- Gibt es spezielle Aktionen (z.B. duplicate, archive, share)?
- Wird Pagination benötigt?

**5. Validierung**
- Welche Felder sind Pflichtfelder?
- Gibt es Längen- oder Format-Beschränkungen?
- Welche Fehler können auftreten?

---

## Feature-Flag System (Zwei-Ebenen Access Control)

### Konzept
1. **Instance-Level (ENV)**: Welche Features existieren auf dieser Instanz?
2. **User-Level (Permissions)**: Was kann dieser User innerhalb der Instance-Limits?

### Definierte Features

| Feature | Modes | ENV-Variable |
|---------|-------|--------------|
| `docker` | disabled, portainer_only, own, full | FEATURE_DOCKER |
| `server` | disabled, own, full | FEATURE_SERVER |
| `tools` | disabled, limited, full | FEATURE_TOOLS |
| `uptime` | disabled, full | FEATURE_UPTIME |
| `invoices` | disabled, full | FEATURE_INVOICES |
| `tickets` | disabled, full | FEATURE_TICKETS |
| `api_tester` | disabled, limited, full | FEATURE_API_TESTER |
| `youtube` | disabled, full | FEATURE_YOUTUBE |
| `passwords` | disabled, full | FEATURE_PASSWORDS |

### Sub-Features (Beispiel Docker)
```php
'docker' => [
    'subFeatures' => [
        'view' => ['portainer_only', 'own', 'full'],
        'hosts_manage' => ['own', 'full'],
        'containers' => ['own', 'full'],
        'system_socket' => ['full'],  // Nur bei full-Mode
        'portainer' => ['portainer_only', 'own', 'full'],
    ],
],
```

---

## Schritt-für-Schritt Anleitungen

### A) Neue öffentliche Seite OHNE Login

**Beispiel: Public Status Page `/status`**

#### 1. Backend: Route hinzufügen
`/backend/src/Core/Router.php` - AUSSERHALB des `->add(AuthMiddleware::class)` Blocks:

```php
// Öffentliche Routes (NACH $group->group('', function...) aber VOR })->add(AuthMiddleware::class))
$group->get('/status', [StatusController::class, 'showPublic']);
```

#### 2. Backend: Controller erstellen
`/backend/src/Modules/Status/Controllers/StatusController.php`:

```php
<?php
declare(strict_types=1);
namespace App\Modules\Status\Controllers;

use App\Core\Http\JsonResponse;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

class StatusController
{
    public function showPublic(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponse::success([
            'status' => 'operational',
            'timestamp' => date('c'),
        ]);
    }
}
```

#### 3. Frontend: Route hinzufügen
`/frontend/src/router/index.js`:

```javascript
// Import
const StatusView = () => import('@/modules/status/views/StatusView.vue')

// Route (mit guest: true oder layout: 'public')
{
  path: '/status',
  name: 'status',
  component: StatusView,
  meta: { layout: 'none', guest: true },  // KEIN Auth erforderlich
},
```

#### 4. Frontend: View erstellen
`/frontend/src/modules/status/views/StatusView.vue`:

```vue
<script setup>
import { ref, onMounted } from 'vue'
import api from '@/core/api/axios'

const status = ref(null)

onMounted(async () => {
  const response = await api.get('/api/v1/status')
  status.value = response.data.data
})
</script>

<template>
  <div class="min-h-screen bg-dark-900 flex items-center justify-center">
    <div class="text-white">Status: {{ status?.status }}</div>
  </div>
</template>
```

**Wichtig:** Bei `meta: { guest: true }` wird die Seite für NICHT-eingeloggte User angezeigt. Bei `meta: { layout: 'public' }` ist die Seite für ALLE zugänglich (eingeloggt oder nicht).

---

### B) Neue geschützte Seite MIT Login

**Beispiel: Notes Module `/notes`**

#### 1. Backend: Migration erstellen
`/backend/database/migrations/XXX_create_notes_table.sql`:

```sql
CREATE TABLE IF NOT EXISTS notes (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    is_pinned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notes_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. Backend: Controller erstellen
`/backend/src/Modules/Notes/Controllers/NoteController.php`:

```php
<?php
declare(strict_types=1);
namespace App\Modules\Notes\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\JsonResponse;
use App\Core\Exceptions\{NotFoundException, ValidationException};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class NoteController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $notes = $this->db->fetchAllAssociative(
            'SELECT * FROM notes WHERE user_id = ? ORDER BY is_pinned DESC, updated_at DESC',
            [$userId]
        );

        return JsonResponse::success(['items' => $notes]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['title'])) {
            throw new ValidationException('Title is required');
        }

        $noteId = Uuid::uuid4()->toString();
        $this->db->insert('notes', [
            'id' => $noteId,
            'user_id' => $userId,
            'title' => $data['title'],
            'content' => $data['content'] ?? '',
        ]);

        $note = $this->db->fetchAssociative('SELECT * FROM notes WHERE id = ?', [$noteId]);
        return JsonResponse::created($note);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $note = $this->db->fetchAssociative(
            'SELECT * FROM notes WHERE id = ? AND user_id = ?',
            [$noteId, $userId]
        );

        if (!$note) {
            throw new NotFoundException('Note not found');
        }

        return JsonResponse::success($note);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $note = $this->db->fetchAssociative(
            'SELECT * FROM notes WHERE id = ? AND user_id = ?',
            [$noteId, $userId]
        );

        if (!$note) {
            throw new NotFoundException('Note not found');
        }

        $updateData = array_filter([
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? null,
            'is_pinned' => isset($data['is_pinned']) ? (bool)$data['is_pinned'] : null,
        ], fn($v) => $v !== null);

        if (!empty($updateData)) {
            $this->db->update('notes', $updateData, ['id' => $noteId]);
        }

        $note = $this->db->fetchAssociative('SELECT * FROM notes WHERE id = ?', [$noteId]);
        return JsonResponse::success($note);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $deleted = $this->db->delete('notes', ['id' => $noteId, 'user_id' => $userId]);

        if ($deleted === 0) {
            throw new NotFoundException('Note not found');
        }

        return JsonResponse::noContent();
    }
}
```

#### 3. Backend: Routes hinzufügen
`/backend/src/Core/Router.php` - INNERHALB des geschützten Blocks:

```php
// Notes - im protected Block (mit AuthMiddleware)
$protected->get('/notes', [NoteController::class, 'index']);
$protected->post('/notes', [NoteController::class, 'create']);
$protected->get('/notes/{id}', [NoteController::class, 'show']);
$protected->put('/notes/{id}', [NoteController::class, 'update']);
$protected->delete('/notes/{id}', [NoteController::class, 'delete']);
```

#### 4. Frontend: Route hinzufügen
`/frontend/src/router/index.js`:

```javascript
const NotesView = () => import('@/modules/notes/views/NotesView.vue')

{
  path: '/notes',
  name: 'notes',
  component: NotesView,
  meta: { requiresAuth: true },  // Login erforderlich
},
```

#### 5. Frontend: View erstellen
`/frontend/src/modules/notes/views/NotesView.vue`:

```vue
<script setup>
import { ref, onMounted } from 'vue'
import { useUiStore } from '@/stores/ui'
import api from '@/core/api/axios'

const uiStore = useUiStore()
const notes = ref([])
const isLoading = ref(false)

async function loadNotes() {
  isLoading.value = true
  try {
    const response = await api.get('/api/v1/notes')
    notes.value = response.data.data?.items || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Notizen')
  } finally {
    isLoading.value = false
  }
}

onMounted(loadNotes)
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold text-white mb-6">Notizen</h1>

    <div v-if="isLoading" class="text-gray-400">Laden...</div>

    <div v-else class="grid gap-4">
      <div
        v-for="note in notes"
        :key="note.id"
        class="bg-dark-800 p-4 rounded-lg"
      >
        <h2 class="text-lg font-semibold text-white">{{ note.title }}</h2>
        <p class="text-gray-400">{{ note.content }}</p>
      </div>
    </div>
  </div>
</template>
```

#### 6. Navigation hinzufügen
`/frontend/src/layouts/components/Sidebar.vue` - In `allNavigationGroups`:

```javascript
// Als eigenständiger Eintrag:
{ id: 'notes', name: 'Notizen', href: '/notes', icon: DocumentTextIcon },

// ODER in einer Gruppe:
{
  id: 'inhalte',
  name: 'Inhalte',
  icon: DocumentDuplicateIcon,
  children: [
    // ... bestehende Einträge
    { name: 'Notizen', href: '/notes', icon: DocumentTextIcon },
  ],
},
```

---

### C) Neues Feature MIT Feature-Flag

**Beispiel: Password Manager mit Feature-Flag**

#### 1. Backend: Feature definieren
`/backend/src/Core/Services/FeatureService.php` - In `FEATURES` konstante:

```php
'passwords' => [
    'modes' => ['disabled', 'full'],
    'default' => 'full',
    'env' => 'FEATURE_PASSWORDS',
    'description' => 'Password manager',
    'subFeatures' => [
        'view' => ['full'],
        'manage' => ['full'],
    ],
],
```

#### 2. Backend: Routes mit FeatureMiddleware
`/backend/src/Core/Router.php`:

```php
// Password Manager - mit Feature-Flag
$protected->get('/passwords', [PasswordController::class, 'index'])
    ->add(new FeatureMiddleware('passwords', null, 'view'));
$protected->post('/passwords', [PasswordController::class, 'create'])
    ->add(new FeatureMiddleware('passwords', null, 'manage'));
$protected->put('/passwords/{id}', [PasswordController::class, 'update'])
    ->add(new FeatureMiddleware('passwords', null, 'manage'));
$protected->delete('/passwords/{id}', [PasswordController::class, 'delete'])
    ->add(new FeatureMiddleware('passwords', null, 'manage'));
```

#### 3. Backend: Permissions-Migration erstellen
`/backend/database/migrations/XXX_add_passwords_permissions.sql`:

```sql
-- Permissions hinzufügen
INSERT INTO permissions (name, description, module) VALUES
('passwords.view', 'View passwords', 'passwords'),
('passwords.manage', 'Manage passwords', 'passwords')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Owner-Rolle bekommt alle Password-Permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'owner' AND p.module = 'passwords'
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;
```

#### 4. Frontend: Feature Store erweitern
`/frontend/src/stores/features.js` - In `subFeatures`:

```javascript
passwords: {
  view: ['full'],
  manage: ['full'],
},
```

#### 5. Frontend: Navigation mit Feature-Check
`/frontend/src/layouts/components/Sidebar.vue`:

```javascript
{
  id: 'security',
  name: 'Sicherheit',
  icon: ShieldCheckIcon,
  feature: 'passwords',  // Gesamte Gruppe nur wenn Feature aktiv
  children: [
    { name: 'Passwörter', href: '/passwords', icon: KeyIcon },
  ],
},
```

---

### D) Admin-Only Seite (Rollen-basiert)

**Beispiel: Audit-Logs nur für Owner**

#### Frontend Route:
```javascript
{
  path: '/admin/audit-logs',
  name: 'audit-logs',
  component: AuditLogsView,
  meta: { requiresAuth: true, roles: ['owner'] },  // Nur Owner
},
```

#### Navigation mit Rollen-Check:
```javascript
{
  id: 'admin',
  name: 'Administration',
  icon: ShieldCheckIcon,
  children: [
    { name: 'Audit Logs', href: '/admin/audit-logs', icon: ClipboardIcon, roles: ['owner'] },
    { name: 'Benutzer', href: '/users', icon: UsersIcon, roles: ['owner', 'admin'] },
  ],
},
```

---

## Code Style Guidelines

### Backend (PHP)

```php
<?php
declare(strict_types=1);  // IMMER am Anfang

namespace App\Modules\{Module}\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\JsonResponse;
use App\Core\Exceptions\{NotFoundException, ValidationException, ForbiddenException};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class ExampleController
{
    // Constructor Injection
    public function __construct(
        private readonly Connection $db,
        private readonly SomeService $service
    ) {}

    // Response-Pattern
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');  // Aus AuthMiddleware

        $items = $this->db->fetchAllAssociative(
            'SELECT * FROM items WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );

        return JsonResponse::success(['items' => $items]);
    }

    // Validation-Pattern
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        // Validierung
        if (empty($data['title'])) {
            throw new ValidationException('Title is required');
        }

        // UUID für neue Einträge
        $id = Uuid::uuid4()->toString();

        // Insert
        $this->db->insert('items', [
            'id' => $id,
            'user_id' => $request->getAttribute('user_id'),
            'title' => $data['title'],
        ]);

        return JsonResponse::created($this->db->fetchAssociative('SELECT * FROM items WHERE id = ?', [$id]));
    }
}
```

### Frontend (Vue.js)

```vue
<script setup>
// Imports gruppiert: Vue, Router, Stores, API, Components, Icons
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import api from '@/core/api/axios'
import { TrashIcon, PencilIcon } from '@heroicons/vue/24/outline'

// Props & Emits
const props = defineProps({
  itemId: { type: String, required: true }
})
const emit = defineEmits(['updated', 'deleted'])

// Stores
const uiStore = useUiStore()
const authStore = useAuthStore()

// State
const isLoading = ref(false)
const items = ref([])
const form = reactive({
  title: '',
  description: '',
})

// API-Calls immer mit try-catch
async function loadItems() {
  isLoading.value = true
  try {
    const response = await api.get('/api/v1/items')
    items.value = response.data.data?.items || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden')
  } finally {
    isLoading.value = false
  }
}

async function createItem() {
  try {
    const response = await api.post('/api/v1/items', form)
    items.value.unshift(response.data.data)
    uiStore.showSuccess('Erfolgreich erstellt')
    resetForm()
  } catch (error) {
    uiStore.showError(error.response?.data?.error || 'Fehler beim Erstellen')
  }
}

async function deleteItem(id) {
  if (!confirm('Wirklich löschen?')) return

  try {
    await api.delete(`/api/v1/items/${id}`)
    items.value = items.value.filter(i => i.id !== id)
    uiStore.showSuccess('Erfolgreich gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

function resetForm() {
  form.title = ''
  form.description = ''
}

onMounted(loadItems)
</script>

<template>
  <div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-white">Titel</h1>
      <button
        @click="showModal = true"
        class="btn-primary"
      >
        Neu erstellen
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-gray-400">Laden...</div>

    <!-- Empty State -->
    <div v-else-if="items.length === 0" class="text-center py-12">
      <p class="text-gray-400">Keine Einträge vorhanden</p>
    </div>

    <!-- List -->
    <div v-else class="space-y-4">
      <div
        v-for="item in items"
        :key="item.id"
        class="bg-dark-800 p-4 rounded-lg flex justify-between items-center"
      >
        <span class="text-white">{{ item.title }}</span>
        <div class="flex gap-2">
          <button @click="editItem(item)" class="text-gray-400 hover:text-white">
            <PencilIcon class="w-5 h-5" />
          </button>
          <button @click="deleteItem(item.id)" class="text-gray-400 hover:text-red-500">
            <TrashIcon class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
```

---

## Common Patterns

### Pagination (Backend)
```php
$page = max(1, (int) ($queryParams['page'] ?? 1));
$perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

$items = $this->db->fetchAllAssociative(
    'SELECT * FROM items WHERE user_id = ? LIMIT ? OFFSET ?',
    [$userId, $perPage, $offset]
);

$total = (int) $this->db->fetchOne(
    'SELECT COUNT(*) FROM items WHERE user_id = ?',
    [$userId]
);

return JsonResponse::paginated($items, $total, $page, $perPage);
```

### Ownership Check
```php
private function getItemForUser(string $itemId, string $userId): array
{
    $item = $this->db->fetchAssociative(
        'SELECT * FROM items WHERE id = ?',
        [$itemId]
    );

    if (!$item) {
        throw new NotFoundException('Item not found');
    }

    if ($item['user_id'] !== $userId) {
        // Optional: Sharing-Check
        $shared = $this->db->fetchAssociative(
            'SELECT * FROM item_shares WHERE item_id = ? AND user_id = ?',
            [$itemId, $userId]
        );
        if (!$shared) {
            throw new ForbiddenException('Access denied');
        }
    }

    return $item;
}
```

### Project-Linking (Frontend)
```javascript
async function createItem() {
  const response = await api.post('/api/v1/items', form)
  const newItem = response.data.data

  // Mit Projekt verknüpfen wenn eines ausgewählt ist
  if (projectStore.selectedProjectId) {
    await projectStore.linkToSelectedProject('item', newItem.id)
  }

  items.value.unshift(newItem)
}
```

### Modal-Pattern
```vue
<script setup>
const showModal = ref(false)
const editingItem = ref(null)

function openCreateModal() {
  editingItem.value = null
  resetForm()
  showModal.value = true
}

function openEditModal(item) {
  editingItem.value = item
  Object.assign(form, item)
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingItem.value = null
  resetForm()
}
</script>
```

---

## Response Patterns

### Backend JsonResponse

```php
// Erfolg mit Daten
return JsonResponse::success($data, 'Optional message');

// Erstellt (201)
return JsonResponse::created($data);

// Kein Inhalt (204) - für DELETE
return JsonResponse::noContent();

// Paginiert
return JsonResponse::paginated($items, $total, $page, $perPage);

// Fehler werden über Exceptions geworfen:
throw new ValidationException('Validation failed');  // 422
throw new AuthException('Invalid credentials');      // 401
throw new ForbiddenException('Access denied');       // 403
throw new NotFoundException('Not found');            // 404
```

### Frontend Error Handling

```javascript
try {
  const response = await api.post('/api/v1/items', data)
  uiStore.showSuccess('Erfolgreich!')
} catch (error) {
  // Spezifische Fehlermeldung vom Backend
  const message = error.response?.data?.error
    || error.response?.data?.message
    || 'Ein Fehler ist aufgetreten'
  uiStore.showError(message)

  // Validation Errors (422)
  if (error.response?.status === 422) {
    errors.value = error.response.data.errors || {}
  }
}
```

---

## Testing & Deployment

### Vor dem Commit prüfen:
1. PHP-Syntax: `php -l backend/src/Modules/...`
2. Frontend-Build: `cd frontend && npm run build`
3. Neue Routen haben passende Middleware
4. Neue Features haben Permissions-Migration
5. Navigation-Einträge haben Feature/Role-Checks

### Migrationen ausführen:
```bash
docker exec kyuubisoft-backend php bin/migrate.php
```

---

## Häufige Fehler & Lösungen

| Problem | Lösung |
|---------|--------|
| 401 Unauthorized | Token abgelaufen → Frontend refresht automatisch |
| 403 Forbidden | Permission fehlt oder Feature deaktiviert |
| Route nicht gefunden | Import in Router.php prüfen |
| Navigation fehlt | Feature-Flag oder Role-Check in Sidebar.vue |
| Leere Daten | user_id Filter prüfen |

---

## Kontakt & Support

Bei Fragen zu:
- **Feature-Implementierung**: Erst alle Fragen aus Abschnitt "Fragen bei Unklarheiten" klären
- **Architektur-Entscheidungen**: Bestehende Module als Referenz nutzen
- **Best Practices**: Diese Dokumentation und bestehenden Code als Vorlage verwenden

---

# Feature-Roadmap: Geplante Erweiterungen

Diese Sektion enthält detaillierte Implementierungspläne für zukünftige Features.

---

## Feature 1: Password Manager

**Status:** Feature-Flag definiert, Implementation ausstehend
**Priorität:** Hoch
**Aufwand:** Mittel (3-5 Tage)

### Beschreibung
Sicherer Passwort-Manager mit AES-256 Verschlüsselung, Kategorien, Passwort-Generator und optionalem Master-Passwort.

### Datenbank-Schema
```sql
-- Migration: 054_create_passwords_tables.sql

CREATE TABLE password_vaults (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL DEFAULT 'Standard',
    master_password_hash VARCHAR(255) NULL,  -- Optional: Extra Schutz
    encryption_key_hash VARCHAR(255) NOT NULL,  -- Für Validierung
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_vault_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_categories (
    id VARCHAR(36) PRIMARY KEY,
    vault_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT 'folder',
    color VARCHAR(7) DEFAULT '#3B82F6',
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vault_id) REFERENCES password_vaults(id) ON DELETE CASCADE,
    INDEX idx_category_vault (vault_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_entries (
    id VARCHAR(36) PRIMARY KEY,
    vault_id VARCHAR(36) NOT NULL,
    category_id VARCHAR(36) NULL,
    title VARCHAR(255) NOT NULL,
    username_encrypted TEXT NULL,
    password_encrypted TEXT NOT NULL,  -- AES-256-GCM verschlüsselt
    url VARCHAR(500) NULL,
    notes_encrypted TEXT NULL,
    custom_fields_encrypted TEXT NULL,  -- JSON: [{"label": "PIN", "value": "encrypted"}]
    is_favorite BOOLEAN DEFAULT FALSE,
    last_used_at TIMESTAMP NULL,
    password_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vault_id) REFERENCES password_vaults(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES password_categories(id) ON DELETE SET NULL,
    INDEX idx_entry_vault (vault_id),
    INDEX idx_entry_category (category_id),
    INDEX idx_entry_favorite (vault_id, is_favorite)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permissions
INSERT INTO permissions (name, description, module) VALUES
('passwords.view', 'View passwords', 'passwords'),
('passwords.manage', 'Manage passwords', 'passwords')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'owner' AND p.module = 'passwords'
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;
```

### Backend API

**Controller:** `/backend/src/Modules/Passwords/Controllers/PasswordController.php`

```php
// Routes (mit FeatureMiddleware 'passwords')
GET    /api/v1/passwords/vaults              // Liste aller Vaults
POST   /api/v1/passwords/vaults              // Vault erstellen
GET    /api/v1/passwords/vaults/{id}         // Vault Details + Entries
DELETE /api/v1/passwords/vaults/{id}         // Vault löschen

GET    /api/v1/passwords/categories          // Kategorien
POST   /api/v1/passwords/categories          // Kategorie erstellen
PUT    /api/v1/passwords/categories/{id}     // Kategorie bearbeiten
DELETE /api/v1/passwords/categories/{id}     // Kategorie löschen

GET    /api/v1/passwords/entries             // Entries (nur Metadaten)
POST   /api/v1/passwords/entries             // Entry erstellen
GET    /api/v1/passwords/entries/{id}        // Entry Details (entschlüsselt)
PUT    /api/v1/passwords/entries/{id}        // Entry bearbeiten
DELETE /api/v1/passwords/entries/{id}        // Entry löschen

POST   /api/v1/passwords/generate            // Passwort generieren
POST   /api/v1/passwords/check-strength      // Passwortstärke prüfen
POST   /api/v1/passwords/unlock              // Vault mit Master-PW entsperren
```

**Service:** `/backend/src/Modules/Passwords/Services/EncryptionService.php`

```php
class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';

    public function encrypt(string $data, string $key): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt($data, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt(string $data, string $key): string
    {
        $data = base64_decode($data);
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $encrypted = substr($data, 28);
        return openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
    }

    public function generatePassword(int $length = 16, array $options = []): string
    {
        $chars = '';
        if ($options['lowercase'] ?? true) $chars .= 'abcdefghijklmnopqrstuvwxyz';
        if ($options['uppercase'] ?? true) $chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($options['numbers'] ?? true) $chars .= '0123456789';
        if ($options['symbols'] ?? true) $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}
```

### Frontend Komponenten

```
/frontend/src/modules/passwords/
├── views/
│   └── PasswordsView.vue       # Hauptansicht mit Sidebar
├── components/
│   ├── VaultSidebar.vue        # Kategorien + Vault-Auswahl
│   ├── PasswordList.vue        # Liste der Einträge
│   ├── PasswordCard.vue        # Einzelner Eintrag
│   ├── PasswordModal.vue       # Erstellen/Bearbeiten
│   ├── PasswordGenerator.vue   # Generator-Dialog
│   └── UnlockModal.vue         # Master-Passwort eingeben
└── stores/
    └── passwords.js            # Pinia Store (optional)
```

### Navigation
```javascript
// Sidebar.vue - Neue Gruppe "Sicherheit"
{
  id: 'security',
  name: 'Sicherheit',
  icon: ShieldCheckIcon,
  feature: 'passwords',
  children: [
    { name: 'Passwörter', href: '/passwords', icon: KeyIcon },
  ],
},
```

### Sicherheitshinweise
- **Encryption Key** wird aus User-Passwort + Salt abgeleitet (PBKDF2)
- **Nie** Klartext-Passwörter in Logs oder Responses
- Master-Passwort optional für Extra-Sicherheit
- Session-basierter Unlock (Timeout nach X Minuten)

---

## Feature 2: Vorlagen-System (Templates)

**Status:** Neu
**Priorität:** Mittel
**Aufwand:** Mittel (3-4 Tage)

### Beschreibung
Wiederverwendbare Vorlagen für Dokumente, Kanban-Boards, Checklisten und Projekte. User können eigene Vorlagen erstellen und System-Vorlagen nutzen.

### Datenbank-Schema
```sql
-- Migration: 055_create_templates_tables.sql

CREATE TABLE templates (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NULL,              -- NULL = System-Template
    type ENUM('document', 'kanban', 'checklist', 'project', 'list') NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) DEFAULT 'template',
    color VARCHAR(7) DEFAULT '#6366F1',
    content JSON NOT NULL,                  -- Template-spezifische Struktur
    is_public BOOLEAN DEFAULT FALSE,        -- Für andere User sichtbar
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_template_user (user_id),
    INDEX idx_template_type (type),
    INDEX idx_template_public (is_public, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Template Content Struktur (JSON)

```javascript
// Document Template
{
  "format": "tiptap",  // oder "markdown"
  "content": "<h1>Titel</h1><p>Inhalt...</p>",
  "variables": [
    { "key": "{{PROJECT_NAME}}", "label": "Projektname", "default": "" },
    { "key": "{{DATE}}", "label": "Datum", "default": "today" }
  ]
}

// Kanban Board Template
{
  "columns": [
    { "name": "Backlog", "color": "#6B7280", "is_completed": false },
    { "name": "In Progress", "color": "#3B82F6", "is_completed": false },
    { "name": "Review", "color": "#F59E0B", "is_completed": false },
    { "name": "Done", "color": "#10B981", "is_completed": true }
  ],
  "tags": [
    { "name": "Bug", "color": "#EF4444" },
    { "name": "Feature", "color": "#3B82F6" }
  ],
  "default_cards": [
    { "title": "Beispiel-Task", "column": 0 }
  ]
}

// Checklist Template
{
  "categories": [
    { "name": "Vorbereitung", "items": ["Item 1", "Item 2"] },
    { "name": "Durchführung", "items": ["Item 3"] }
  ],
  "settings": {
    "require_name": true,
    "allow_comments": true
  }
}

// Project Template
{
  "default_links": ["kanban", "document"],
  "default_kanban_template": "uuid-of-kanban-template",
  "default_documents": [
    { "title": "README", "template": "uuid-of-doc-template" }
  ]
}
```

### Backend API

```php
// Routes
GET    /api/v1/templates                    // Liste (mit ?type= Filter)
GET    /api/v1/templates/system             // System-Templates
POST   /api/v1/templates                    // Template erstellen
GET    /api/v1/templates/{id}               // Template Details
PUT    /api/v1/templates/{id}               // Template bearbeiten
DELETE /api/v1/templates/{id}               // Template löschen
POST   /api/v1/templates/{id}/use           // Template anwenden → neues Item erstellen
POST   /api/v1/templates/from/{type}/{id}   // Aus bestehendem Item Template erstellen
```

### Frontend Integration

```vue
<!-- TemplateSelector.vue - Wiederverwendbare Komponente -->
<script setup>
const props = defineProps({
  type: { type: String, required: true },  // 'document', 'kanban', etc.
})

const emit = defineEmits(['select'])

const templates = ref([])
const showModal = ref(false)

async function loadTemplates() {
  const response = await api.get('/api/v1/templates', { params: { type: props.type } })
  templates.value = response.data.data.items
}

async function useTemplate(template) {
  const response = await api.post(`/api/v1/templates/${template.id}/use`)
  emit('select', response.data.data)  // Neues Item
  showModal.value = false
}
</script>
```

**Integration in bestehende Views:**
```vue
<!-- DocumentsView.vue -->
<button @click="showTemplateSelector = true">
  Aus Vorlage erstellen
</button>

<TemplateSelector
  v-if="showTemplateSelector"
  type="document"
  @select="handleTemplateCreated"
  @close="showTemplateSelector = false"
/>
```

---

## Feature 3: Wiederkehrende Aufgaben (Recurring Tasks)

**Status:** Neu
**Priorität:** Mittel
**Aufwand:** Mittel (3-4 Tage)

### Beschreibung
Automatisch wiederkehrende Tasks in Listen und Kanban-Boards. Unterstützt tägliche, wöchentliche, monatliche und benutzerdefinierte Intervalle.

### Datenbank-Schema
```sql
-- Migration: 056_create_recurring_tasks.sql

CREATE TABLE recurring_tasks (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    source_type ENUM('list_item', 'kanban_card') NOT NULL,
    source_id VARCHAR(36) NOT NULL,         -- Original Item/Card ID
    target_list_id VARCHAR(36) NULL,        -- Für Listen
    target_board_id VARCHAR(36) NULL,       -- Für Kanban
    target_column_id VARCHAR(36) NULL,      -- Für Kanban

    -- Recurrence Pattern
    frequency ENUM('daily', 'weekly', 'monthly', 'yearly', 'custom') NOT NULL,
    interval_value INT DEFAULT 1,           -- Alle X Tage/Wochen/etc.
    weekdays JSON NULL,                     -- [1,3,5] für Mo, Mi, Fr
    month_day INT NULL,                     -- 1-31 für monatlich

    -- Timing
    start_date DATE NOT NULL,
    end_date DATE NULL,                     -- NULL = unendlich
    next_occurrence DATE NOT NULL,
    last_created_at TIMESTAMP NULL,

    -- Options
    title_template VARCHAR(255) NOT NULL,   -- "Weekly Review - {{DATE}}"
    copy_description BOOLEAN DEFAULT TRUE,
    copy_checklist BOOLEAN DEFAULT FALSE,
    auto_assign BOOLEAN DEFAULT TRUE,       -- Gleichen User zuweisen

    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_recurring_next (next_occurrence, is_active),
    INDEX idx_recurring_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recurring_task_history (
    id VARCHAR(36) PRIMARY KEY,
    recurring_task_id VARCHAR(36) NOT NULL,
    created_item_type VARCHAR(20) NOT NULL,
    created_item_id VARCHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recurring_task_id) REFERENCES recurring_tasks(id) ON DELETE CASCADE,
    INDEX idx_history_task (recurring_task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Backend Scheduler

**Cronjob:** `/backend/bin/recurring-tasks.php` (alle 15 Minuten)

```php
<?php
// Finde alle fälligen Tasks
$tasks = $db->fetchAllAssociative(
    'SELECT * FROM recurring_tasks
     WHERE is_active = TRUE AND next_occurrence <= CURDATE()',
    []
);

foreach ($tasks as $task) {
    try {
        // Task erstellen
        $newItemId = $this->createFromRecurring($task);

        // History speichern
        $db->insert('recurring_task_history', [
            'id' => Uuid::uuid4()->toString(),
            'recurring_task_id' => $task['id'],
            'created_item_type' => $task['source_type'],
            'created_item_id' => $newItemId,
        ]);

        // Nächstes Datum berechnen
        $nextDate = $this->calculateNextOccurrence($task);

        if ($nextDate && (!$task['end_date'] || $nextDate <= $task['end_date'])) {
            $db->update('recurring_tasks', [
                'next_occurrence' => $nextDate,
                'last_created_at' => date('Y-m-d H:i:s'),
            ], ['id' => $task['id']]);
        } else {
            // Deaktivieren wenn end_date erreicht
            $db->update('recurring_tasks', ['is_active' => false], ['id' => $task['id']]);
        }
    } catch (\Exception $e) {
        $logger->error('Recurring task failed', ['task_id' => $task['id'], 'error' => $e->getMessage()]);
    }
}
```

### Backend API

```php
// Routes
GET    /api/v1/recurring                    // Alle wiederkehrenden Tasks
POST   /api/v1/recurring                    // Neuen erstellen
GET    /api/v1/recurring/{id}               // Details
PUT    /api/v1/recurring/{id}               // Bearbeiten
DELETE /api/v1/recurring/{id}               // Löschen
POST   /api/v1/recurring/{id}/pause         // Pausieren
POST   /api/v1/recurring/{id}/resume        // Fortsetzen
GET    /api/v1/recurring/{id}/history       // Erstellte Items
```

### Frontend Komponenten

```
/frontend/src/components/recurring/
├── RecurringModal.vue          # Erstellen/Bearbeiten
├── RecurringBadge.vue          # Kleines Icon auf Items
├── RecurringManager.vue        # Übersicht aller Tasks
└── FrequencySelector.vue       # Frequenz-Auswahl
```

### UI Integration

```vue
<!-- In ListItemCard.vue oder KanbanCard.vue -->
<template>
  <div class="card">
    <RecurringBadge v-if="item.recurring_id" :recurringId="item.recurring_id" />
    <!-- ... -->
    <button @click="showRecurringModal = true">
      <ArrowPathIcon class="w-4 h-4" />
      Wiederholen
    </button>
  </div>
</template>
```

---

## Feature 4: API Keys für externe Integration

**Status:** Neu
**Priorität:** Hoch
**Aufwand:** Klein (1-2 Tage)

### Beschreibung
Persönliche API-Keys für Automatisierung und externe Tool-Integration. Unterstützt Scopes für granulare Berechtigungen.

### Datenbank-Schema
```sql
-- Migration: 057_create_api_keys.sql

CREATE TABLE api_keys (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    key_hash VARCHAR(255) NOT NULL,         -- SHA-256 Hash des Keys
    key_prefix VARCHAR(8) NOT NULL,         -- Erste 8 Zeichen für Identifikation
    scopes JSON NOT NULL,                   -- ["lists.read", "lists.write", ...]
    last_used_at TIMESTAMP NULL,
    last_used_ip VARCHAR(45) NULL,
    expires_at TIMESTAMP NULL,              -- NULL = nie
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_api_key_hash (key_hash),
    INDEX idx_api_key_user (user_id),
    INDEX idx_api_key_prefix (key_prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Backend Implementation

**Key-Generierung:**
```php
class ApiKeyService
{
    public function generate(string $userId, string $name, array $scopes): array
    {
        // Format: ks_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX (32 Zeichen nach Prefix)
        $key = 'ks_' . bin2hex(random_bytes(16));
        $prefix = substr($key, 0, 11);  // "ks_XXXXXXXX"

        $this->db->insert('api_keys', [
            'id' => Uuid::uuid4()->toString(),
            'user_id' => $userId,
            'name' => $name,
            'key_hash' => hash('sha256', $key),
            'key_prefix' => $prefix,
            'scopes' => json_encode($scopes),
        ]);

        // Key nur einmal anzeigen!
        return [
            'key' => $key,
            'prefix' => $prefix,
            'scopes' => $scopes,
        ];
    }
}
```

**ApiKeyMiddleware:**
```php
class ApiKeyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $apiKey = $request->getHeaderLine('X-API-Key');

        if (empty($apiKey)) {
            return $handler->handle($request);  // Weiter zu JWT-Auth
        }

        $keyData = $this->db->fetchAssociative(
            'SELECT ak.*, u.id as user_id, u.email
             FROM api_keys ak
             JOIN users u ON ak.user_id = u.id
             WHERE ak.key_hash = ? AND ak.is_active = TRUE
             AND (ak.expires_at IS NULL OR ak.expires_at > NOW())',
            [hash('sha256', $apiKey)]
        );

        if (!$keyData) {
            return JsonResponse::unauthorized('Invalid API key');
        }

        // Update last_used
        $this->db->update('api_keys', [
            'last_used_at' => date('Y-m-d H:i:s'),
            'last_used_ip' => $request->getServerParams()['REMOTE_ADDR'] ?? null,
        ], ['id' => $keyData['id']]);

        // User-Info an Request anhängen
        return $handler->handle($request
            ->withAttribute('user_id', $keyData['user_id'])
            ->withAttribute('api_key_scopes', json_decode($keyData['scopes'], true))
            ->withAttribute('auth_type', 'api_key')
        );
    }
}
```

### API Routes

```php
// Routes (unter /settings)
GET    /api/v1/settings/api-keys            // Liste der Keys (ohne Key selbst)
POST   /api/v1/settings/api-keys            // Key erstellen → Key einmalig anzeigen
DELETE /api/v1/settings/api-keys/{id}       // Key löschen/widerrufen
PUT    /api/v1/settings/api-keys/{id}       // Name/Scopes ändern
```

### Frontend

```vue
<!-- In SettingsView.vue - Neuer Tab "API Keys" -->
<template>
  <div>
    <h2>API Keys</h2>
    <p class="text-gray-400">
      Erstelle API-Keys für externe Tools und Automatisierung.
    </p>

    <div v-for="key in apiKeys" :key="key.id" class="bg-dark-800 p-4 rounded-lg">
      <div class="flex justify-between">
        <div>
          <span class="font-mono text-gray-400">{{ key.key_prefix }}...</span>
          <span class="ml-2 text-white">{{ key.name }}</span>
        </div>
        <button @click="revokeKey(key.id)" class="text-red-400">Widerrufen</button>
      </div>
      <div class="text-xs text-gray-500 mt-1">
        Zuletzt verwendet: {{ key.last_used_at || 'Nie' }}
      </div>
    </div>

    <!-- Modal für neuen Key mit Scope-Auswahl -->
  </div>
</template>
```

### Scope-System

```javascript
// Verfügbare Scopes
const AVAILABLE_SCOPES = {
  'lists.read': 'Listen lesen',
  'lists.write': 'Listen erstellen/bearbeiten',
  'documents.read': 'Dokumente lesen',
  'documents.write': 'Dokumente erstellen/bearbeiten',
  'kanban.read': 'Kanban-Boards lesen',
  'kanban.write': 'Kanban-Boards bearbeiten',
  'time.read': 'Zeiteinträge lesen',
  'time.write': 'Zeiteinträge erstellen',
  'uptime.read': 'Uptime-Daten lesen',
  // ... weitere
}
```

---

## Feature 5: Export/Import System

**Status:** Neu
**Priorität:** Hoch
**Aufwand:** Mittel (2-3 Tage)

### Beschreibung
Daten-Export (JSON/CSV) für Backups und Migration. Import für Wiederherstellung und Datenmigration von anderen Tools.

### Backend API

```php
// Export Routes
GET    /api/v1/export/lists                 // Alle Listen als JSON
GET    /api/v1/export/lists/{id}            // Einzelne Liste
GET    /api/v1/export/documents             // Alle Dokumente
GET    /api/v1/export/kanban                // Alle Boards
GET    /api/v1/export/time?format=csv       // Zeiteinträge als CSV
GET    /api/v1/export/all                   // Kompletter Backup (ZIP)

// Import Routes
POST   /api/v1/import/lists                 // Listen importieren
POST   /api/v1/import/documents             // Dokumente importieren
POST   /api/v1/import/kanban                // Kanban-Boards importieren
POST   /api/v1/import/todoist               // Von Todoist importieren
POST   /api/v1/import/trello                // Von Trello importieren
```

### Export Format (JSON)

```json
{
  "version": "1.0",
  "exported_at": "2024-01-15T10:30:00Z",
  "type": "lists",
  "data": [
    {
      "id": "uuid",
      "title": "Meine Liste",
      "description": "...",
      "type": "todo",
      "items": [
        {
          "id": "uuid",
          "content": "Task 1",
          "is_completed": false,
          "due_date": null
        }
      ]
    }
  ]
}
```

### Export Service

```php
class ExportService
{
    public function exportAll(string $userId): string
    {
        $tempDir = sys_get_temp_dir() . '/export_' . Uuid::uuid4()->toString();
        mkdir($tempDir);

        // Einzelne Exporte
        file_put_contents($tempDir . '/lists.json', json_encode($this->exportLists($userId)));
        file_put_contents($tempDir . '/documents.json', json_encode($this->exportDocuments($userId)));
        file_put_contents($tempDir . '/kanban.json', json_encode($this->exportKanban($userId)));
        file_put_contents($tempDir . '/time.json', json_encode($this->exportTime($userId)));
        file_put_contents($tempDir . '/bookmarks.json', json_encode($this->exportBookmarks($userId)));

        // ZIP erstellen
        $zipPath = sys_get_temp_dir() . '/kyuubisoft_backup_' . date('Y-m-d') . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);

        foreach (glob($tempDir . '/*') as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        // Cleanup
        array_map('unlink', glob($tempDir . '/*'));
        rmdir($tempDir);

        return $zipPath;
    }

    public function exportListsAsCsv(string $userId): string
    {
        $lists = $this->db->fetchAllAssociative(
            'SELECT l.*, li.content as item_content, li.is_completed
             FROM lists l
             LEFT JOIN list_items li ON l.id = li.list_id
             WHERE l.user_id = ?
             ORDER BY l.title, li.position',
            [$userId]
        );

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Liste', 'Beschreibung', 'Item', 'Erledigt']);

        foreach ($lists as $row) {
            fputcsv($csv, [
                $row['title'],
                $row['description'],
                $row['item_content'],
                $row['is_completed'] ? 'Ja' : 'Nein'
            ]);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return $content;
    }
}
```

### Import Service

```php
class ImportService
{
    public function importFromTodoist(string $userId, array $data): array
    {
        $imported = ['lists' => 0, 'items' => 0];

        foreach ($data['projects'] as $project) {
            $listId = Uuid::uuid4()->toString();
            $this->db->insert('lists', [
                'id' => $listId,
                'user_id' => $userId,
                'title' => $project['name'],
                'type' => 'todo',
            ]);
            $imported['lists']++;

            foreach ($project['items'] ?? [] as $position => $item) {
                $this->db->insert('list_items', [
                    'id' => Uuid::uuid4()->toString(),
                    'list_id' => $listId,
                    'content' => $item['content'],
                    'is_completed' => $item['checked'] ?? false,
                    'position' => $position,
                ]);
                $imported['items']++;
            }
        }

        return $imported;
    }
}
```

### Frontend

```vue
<!-- ExportImportView.vue -->
<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold text-white mb-6">Daten Export/Import</h1>

    <!-- Export -->
    <div class="bg-dark-800 p-6 rounded-lg mb-6">
      <h2 class="text-lg font-semibold text-white mb-4">Export</h2>

      <div class="grid grid-cols-2 gap-4">
        <button @click="exportAll" class="btn-primary">
          Komplettes Backup (ZIP)
        </button>
        <button @click="exportLists('json')" class="btn-secondary">
          Listen (JSON)
        </button>
        <button @click="exportTime('csv')" class="btn-secondary">
          Zeiteinträge (CSV)
        </button>
      </div>
    </div>

    <!-- Import -->
    <div class="bg-dark-800 p-6 rounded-lg">
      <h2 class="text-lg font-semibold text-white mb-4">Import</h2>

      <div class="space-y-4">
        <div>
          <label class="block text-sm text-gray-400 mb-2">KyuubiSoft Backup</label>
          <input type="file" accept=".json,.zip" @change="handleImport" />
        </div>

        <div class="border-t border-dark-600 pt-4">
          <p class="text-gray-400 mb-2">Von anderen Apps importieren:</p>
          <div class="flex gap-2">
            <button @click="showTodoistImport = true" class="btn-secondary">
              Todoist
            </button>
            <button @click="showTrelloImport = true" class="btn-secondary">
              Trello
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
```

---

## Feature 6: Favoriten/Schnellzugriff

**Status:** Neu
**Priorität:** Mittel
**Aufwand:** Klein (1-2 Tage)

### Beschreibung
Globale Favoriten-Leiste für schnellen Zugriff auf häufig genutzte Items aller Module.

### Datenbank-Schema
```sql
-- Migration: 058_create_favorites.sql

CREATE TABLE favorites (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    item_type VARCHAR(50) NOT NULL,         -- 'list', 'document', 'kanban_board', etc.
    item_id VARCHAR(36) NOT NULL,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_favorite_unique (user_id, item_type, item_id),
    INDEX idx_favorite_user (user_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Backend API

```php
// Routes
GET    /api/v1/favorites                    // Alle Favoriten mit Item-Details
POST   /api/v1/favorites                    // Favorit hinzufügen
DELETE /api/v1/favorites/{type}/{id}        // Favorit entfernen
PUT    /api/v1/favorites/reorder            // Reihenfolge ändern
```

### Controller

```php
public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    $userId = $request->getAttribute('user_id');

    $favorites = $this->db->fetchAllAssociative(
        'SELECT * FROM favorites WHERE user_id = ? ORDER BY position',
        [$userId]
    );

    // Item-Details laden
    foreach ($favorites as &$fav) {
        $fav['item'] = $this->getItemDetails($fav['item_type'], $fav['item_id']);
    }

    return JsonResponse::success(['items' => $favorites]);
}

private function getItemDetails(string $type, string $id): ?array
{
    return match($type) {
        'list' => $this->db->fetchAssociative('SELECT id, title, color FROM lists WHERE id = ?', [$id]),
        'document' => $this->db->fetchAssociative('SELECT id, title FROM documents WHERE id = ?', [$id]),
        'kanban_board' => $this->db->fetchAssociative('SELECT id, name as title, color FROM kanban_boards WHERE id = ?', [$id]),
        'project' => $this->db->fetchAssociative('SELECT id, name as title, color FROM projects WHERE id = ?', [$id]),
        'checklist' => $this->db->fetchAssociative('SELECT id, name as title FROM shared_checklists WHERE id = ?', [$id]),
        default => null,
    };
}
```

### Frontend

```vue
<!-- FavoritesBar.vue - Im DefaultLayout über dem Content -->
<template>
  <div v-if="favorites.length > 0" class="bg-dark-800 border-b border-dark-700 px-4 py-2">
    <div class="flex items-center gap-2 overflow-x-auto">
      <StarIcon class="w-4 h-4 text-yellow-500 flex-shrink-0" />

      <button
        v-for="fav in favorites"
        :key="fav.id"
        @click="navigateTo(fav)"
        class="flex items-center gap-2 px-3 py-1 bg-dark-700 rounded-lg hover:bg-dark-600 transition-colors flex-shrink-0"
      >
        <component :is="getIcon(fav.item_type)" class="w-4 h-4 text-gray-400" />
        <span class="text-sm text-white">{{ fav.item?.title }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { useFavoritesStore } from '@/stores/favorites'

const favoritesStore = useFavoritesStore()
const favorites = computed(() => favoritesStore.items)

function navigateTo(fav) {
  const routes = {
    list: `/lists`,
    document: `/documents`,
    kanban_board: `/kanban`,
    project: `/projects`,
    checklist: `/checklists/${fav.item_id}`,
  }
  router.push(routes[fav.item_type] || '/')
}
</script>
```

### Toggle in Views

```vue
<!-- In DocumentsView.vue, ListsView.vue, etc. -->
<button
  @click="toggleFavorite(item)"
  :class="isFavorite(item) ? 'text-yellow-500' : 'text-gray-400'"
>
  <StarIcon class="w-5 h-5" :class="{ 'fill-current': isFavorite(item) }" />
</button>
```

---

## Feature 7: Globale Tags

**Status:** Neu
**Priorität:** Mittel
**Aufwand:** Mittel (2-3 Tage)

### Beschreibung
Modulübergreifendes Tag-System. Ein Tag kann auf Dokumente, Listen, Kanban-Boards, Projekte etc. angewendet werden.

### Datenbank-Schema
```sql
-- Migration: 059_create_global_tags.sql

CREATE TABLE global_tags (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7) DEFAULT '#6366F1',
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_tag_unique (user_id, name),
    INDEX idx_tag_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE global_tag_items (
    id VARCHAR(36) PRIMARY KEY,
    tag_id VARCHAR(36) NOT NULL,
    item_type VARCHAR(50) NOT NULL,
    item_id VARCHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tag_id) REFERENCES global_tags(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_tag_item_unique (tag_id, item_type, item_id),
    INDEX idx_tag_items_tag (tag_id),
    INDEX idx_tag_items_item (item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Backend API

```php
// Tag Management
GET    /api/v1/tags                         // Alle Tags
POST   /api/v1/tags                         // Tag erstellen
PUT    /api/v1/tags/{id}                    // Tag bearbeiten
DELETE /api/v1/tags/{id}                    // Tag löschen

// Tag-Item Verknüpfung
POST   /api/v1/tags/{id}/items              // Item zu Tag hinzufügen
DELETE /api/v1/tags/{id}/items/{type}/{itemId}  // Item von Tag entfernen
GET    /api/v1/tags/{id}/items              // Alle Items mit diesem Tag

// Suche nach Tags
GET    /api/v1/search?tags=tag1,tag2        // Items mit bestimmten Tags
```

### Frontend Komponenten

```vue
<!-- GlobalTagSelector.vue -->
<template>
  <div class="relative">
    <div class="flex flex-wrap gap-1">
      <span
        v-for="tag in selectedTags"
        :key="tag.id"
        class="inline-flex items-center px-2 py-1 rounded-full text-xs"
        :style="{ backgroundColor: tag.color + '20', color: tag.color }"
      >
        {{ tag.name }}
        <button @click="removeTag(tag)" class="ml-1">&times;</button>
      </span>

      <button @click="showDropdown = true" class="text-gray-400 hover:text-white">
        <PlusIcon class="w-4 h-4" />
      </button>
    </div>

    <!-- Dropdown mit Suche und Erstellung -->
    <div v-if="showDropdown" class="absolute z-50 mt-1 bg-dark-700 rounded-lg shadow-xl">
      <input
        v-model="searchQuery"
        placeholder="Tag suchen oder erstellen..."
        class="w-full px-3 py-2 bg-transparent border-b border-dark-600"
      />

      <div class="max-h-48 overflow-y-auto">
        <button
          v-for="tag in filteredTags"
          :key="tag.id"
          @click="addTag(tag)"
          class="w-full px-3 py-2 text-left hover:bg-dark-600"
        >
          <span class="w-3 h-3 rounded-full inline-block mr-2" :style="{ backgroundColor: tag.color }"></span>
          {{ tag.name }}
        </button>

        <button
          v-if="searchQuery && !tagExists"
          @click="createAndAddTag"
          class="w-full px-3 py-2 text-left text-primary-400 hover:bg-dark-600"
        >
          + "{{ searchQuery }}" erstellen
        </button>
      </div>
    </div>
  </div>
</template>
```

### Tag-Filter im Dashboard

```vue
<!-- TagFilter.vue - Für globale Filterung -->
<template>
  <div class="flex items-center gap-2 mb-4">
    <span class="text-gray-400">Filter:</span>
    <button
      v-for="tag in popularTags"
      :key="tag.id"
      @click="toggleTagFilter(tag)"
      class="px-2 py-1 rounded-full text-xs transition-colors"
      :class="isTagActive(tag) ? 'bg-primary-600 text-white' : 'bg-dark-700 text-gray-400'"
    >
      {{ tag.name }}
    </button>
  </div>
</template>
```

---

## Feature 8: Dashboard-Erweiterungen

**Status:** Neu
**Priorität:** Niedrig
**Aufwand:** Mittel (2-3 Tage)

### Beschreibung
Erweiterte Dashboard-Widgets: Embedded Checkliste, Quick-Add für alle Module, Kalender-Widget mit Wochenansicht.

### Neue Widget-Typen

```php
// WidgetController.php - Verfügbare Widgets erweitern
const AVAILABLE_WIDGETS = [
    // Bestehende
    'recent_documents' => ['name' => 'Letzte Dokumente', 'size' => 'medium'],
    'recent_lists' => ['name' => 'Letzte Listen', 'size' => 'medium'],
    'time_stats' => ['name' => 'Zeitstatistik', 'size' => 'large'],
    'uptime_status' => ['name' => 'Uptime Status', 'size' => 'medium'],

    // NEU
    'quick_add' => [
        'name' => 'Schnell-Erstellen',
        'size' => 'small',
        'description' => 'Quick-Add für alle Module',
    ],
    'embedded_checklist' => [
        'name' => 'Eingebettete Checkliste',
        'size' => 'medium',
        'config' => ['checklist_id' => null],
    ],
    'calendar_week' => [
        'name' => 'Wochenkalender',
        'size' => 'large',
        'description' => 'Kalender mit Wochenansicht',
    ],
    'kanban_summary' => [
        'name' => 'Kanban Übersicht',
        'size' => 'medium',
        'description' => 'Status aller Boards',
    ],
    'favorites_quick' => [
        'name' => 'Favoriten',
        'size' => 'small',
    ],
    'project_progress' => [
        'name' => 'Projekt-Fortschritt',
        'size' => 'medium',
        'config' => ['project_id' => null],
    ],
];
```

### Widget-Komponenten

```vue
<!-- QuickAddWidget.vue -->
<template>
  <div class="bg-dark-800 p-4 rounded-lg">
    <h3 class="font-semibold text-white mb-3">Schnell erstellen</h3>

    <div class="grid grid-cols-2 gap-2">
      <button @click="quickCreate('list')" class="p-3 bg-dark-700 rounded-lg hover:bg-dark-600">
        <ListBulletIcon class="w-5 h-5 text-blue-400 mx-auto" />
        <span class="text-xs text-gray-400">Liste</span>
      </button>
      <button @click="quickCreate('document')" class="p-3 bg-dark-700 rounded-lg hover:bg-dark-600">
        <DocumentIcon class="w-5 h-5 text-green-400 mx-auto" />
        <span class="text-xs text-gray-400">Dokument</span>
      </button>
      <button @click="quickCreate('kanban')" class="p-3 bg-dark-700 rounded-lg hover:bg-dark-600">
        <ViewColumnsIcon class="w-5 h-5 text-purple-400 mx-auto" />
        <span class="text-xs text-gray-400">Board</span>
      </button>
      <button @click="quickCreate('time')" class="p-3 bg-dark-700 rounded-lg hover:bg-dark-600">
        <ClockIcon class="w-5 h-5 text-orange-400 mx-auto" />
        <span class="text-xs text-gray-400">Zeit</span>
      </button>
    </div>
  </div>
</template>
```

```vue
<!-- EmbeddedChecklistWidget.vue -->
<template>
  <div class="bg-dark-800 p-4 rounded-lg">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold text-white">{{ checklist?.name }}</h3>
      <router-link :to="`/checklists/${config.checklist_id}`" class="text-primary-400 text-sm">
        Öffnen →
      </router-link>
    </div>

    <div class="space-y-2 max-h-64 overflow-y-auto">
      <div
        v-for="item in checklist?.items?.slice(0, 10)"
        :key="item.id"
        class="flex items-center gap-2"
      >
        <input
          type="checkbox"
          :checked="item.is_checked"
          @change="toggleItem(item)"
          class="rounded"
        />
        <span :class="item.is_checked ? 'line-through text-gray-500' : 'text-white'">
          {{ item.content }}
        </span>
      </div>
    </div>
  </div>
</template>
```

---

## Feature 9: Benachrichtigungs-Kanäle

**Status:** Neu
**Priorität:** Mittel
**Aufwand:** Mittel (3-4 Tage)

### Beschreibung
Erweiterte Benachrichtigungen via Email, Push (PWA), Telegram und Discord.

### Datenbank-Schema
```sql
-- Migration: 060_create_notification_channels.sql

CREATE TABLE notification_channels (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    type ENUM('email', 'push', 'telegram', 'discord', 'webhook') NOT NULL,
    config JSON NOT NULL,                   -- Channel-spezifische Konfiguration
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_channel_user (user_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Beispiel Configs:
-- Email: {"address": "user@example.com"}
-- Telegram: {"chat_id": "123456789", "bot_token": "..."}
-- Discord: {"webhook_url": "https://discord.com/api/webhooks/..."}
-- Push: {"endpoint": "...", "keys": {"p256dh": "...", "auth": "..."}}

CREATE TABLE notification_preferences (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    event_type VARCHAR(100) NOT NULL,       -- 'uptime.down', 'ticket.new', etc.
    channels JSON NOT NULL,                 -- ["email", "push"]
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_pref_unique (user_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Notification Service

```php
class NotificationService
{
    public function send(string $userId, string $eventType, array $data): void
    {
        // Präferenzen laden
        $prefs = $this->db->fetchAssociative(
            'SELECT channels FROM notification_preferences WHERE user_id = ? AND event_type = ?',
            [$userId, $eventType]
        );

        if (!$prefs) {
            return;  // Keine Benachrichtigung gewünscht
        }

        $channels = json_decode($prefs['channels'], true);

        foreach ($channels as $channelType) {
            $channel = $this->db->fetchAssociative(
                'SELECT * FROM notification_channels WHERE user_id = ? AND type = ? AND is_active = TRUE',
                [$userId, $channelType]
            );

            if ($channel) {
                $this->sendToChannel($channel, $eventType, $data);
            }
        }
    }

    private function sendToChannel(array $channel, string $eventType, array $data): void
    {
        $config = json_decode($channel['config'], true);

        match($channel['type']) {
            'email' => $this->sendEmail($config['address'], $eventType, $data),
            'telegram' => $this->sendTelegram($config['chat_id'], $config['bot_token'], $data),
            'discord' => $this->sendDiscord($config['webhook_url'], $data),
            'push' => $this->sendPush($config, $data),
            'webhook' => $this->sendWebhook($config['url'], $data),
        };
    }

    private function sendTelegram(string $chatId, string $botToken, array $data): void
    {
        $message = $this->formatMessage($data);

        file_get_contents("https://api.telegram.org/bot{$botToken}/sendMessage?" . http_build_query([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]));
    }

    private function sendDiscord(string $webhookUrl, array $data): void
    {
        $client = new \GuzzleHttp\Client();
        $client->post($webhookUrl, [
            'json' => [
                'content' => $data['message'],
                'embeds' => [[
                    'title' => $data['title'],
                    'description' => $data['description'] ?? '',
                    'color' => $this->getColorForEvent($data['event_type']),
                ]],
            ],
        ]);
    }
}
```

### Frontend

```vue
<!-- NotificationSettingsView.vue -->
<template>
  <div class="space-y-6">
    <!-- Kanäle -->
    <div class="bg-dark-800 p-6 rounded-lg">
      <h2 class="text-lg font-semibold text-white mb-4">Benachrichtigungs-Kanäle</h2>

      <!-- Email -->
      <div class="flex items-center justify-between py-3 border-b border-dark-700">
        <div class="flex items-center gap-3">
          <EnvelopeIcon class="w-5 h-5 text-gray-400" />
          <div>
            <span class="text-white">Email</span>
            <span v-if="emailChannel" class="text-xs text-gray-400 ml-2">
              {{ emailChannel.config.address }}
            </span>
          </div>
        </div>
        <button @click="configureEmail" class="btn-secondary">
          {{ emailChannel ? 'Ändern' : 'Einrichten' }}
        </button>
      </div>

      <!-- Telegram -->
      <div class="flex items-center justify-between py-3 border-b border-dark-700">
        <div class="flex items-center gap-3">
          <TelegramIcon class="w-5 h-5 text-blue-400" />
          <span class="text-white">Telegram</span>
        </div>
        <button @click="configureTelegram" class="btn-secondary">
          {{ telegramChannel ? 'Verbunden' : 'Verbinden' }}
        </button>
      </div>

      <!-- Discord -->
      <div class="flex items-center justify-between py-3">
        <div class="flex items-center gap-3">
          <DiscordIcon class="w-5 h-5 text-indigo-400" />
          <span class="text-white">Discord</span>
        </div>
        <button @click="configureDiscord" class="btn-secondary">
          {{ discordChannel ? 'Verbunden' : 'Verbinden' }}
        </button>
      </div>
    </div>

    <!-- Präferenzen -->
    <div class="bg-dark-800 p-6 rounded-lg">
      <h2 class="text-lg font-semibold text-white mb-4">Benachrichtigungen</h2>

      <div v-for="event in eventTypes" :key="event.key" class="py-3 border-b border-dark-700 last:border-0">
        <div class="flex justify-between items-center">
          <span class="text-white">{{ event.label }}</span>
          <div class="flex gap-2">
            <button
              v-for="channel in ['email', 'telegram', 'discord']"
              :key="channel"
              @click="togglePreference(event.key, channel)"
              :class="isEnabled(event.key, channel) ? 'bg-primary-600' : 'bg-dark-700'"
              class="px-2 py-1 rounded text-xs"
            >
              {{ channel }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
```

---

## Feature 10: Datei-Versionierung (Cloud Storage)

**Status:** Neu
**Priorität:** Mittel
**Aufwand:** Mittel (2-3 Tage)

### Beschreibung
Versionskontrolle für Cloud Storage Dateien. Bei Überschreiben wird alte Version aufbewahrt.

### Datenbank-Schema
```sql
-- Migration: 061_add_storage_versions.sql

CREATE TABLE storage_file_versions (
    id VARCHAR(36) PRIMARY KEY,
    file_id VARCHAR(36) NOT NULL,
    version_number INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,        -- Pfad zur versionierten Datei
    file_size BIGINT NOT NULL,
    checksum VARCHAR(64) NOT NULL,          -- SHA-256
    created_by VARCHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES storage_files(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_version_file (file_id, version_number DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Spalte zur Haupttabelle
ALTER TABLE storage_files ADD COLUMN current_version INT DEFAULT 1;
ALTER TABLE storage_files ADD COLUMN max_versions INT DEFAULT 10;
```

### Backend Implementation

```php
class StorageService
{
    public function updateFile(string $fileId, UploadedFile $newFile, string $userId): array
    {
        $file = $this->db->fetchAssociative('SELECT * FROM storage_files WHERE id = ?', [$fileId]);

        // Alte Version sichern
        $this->createVersion($file);

        // Neue Datei speichern
        $newPath = $this->saveFile($newFile, $file['folder_path']);

        // Update
        $this->db->update('storage_files', [
            'file_path' => $newPath,
            'file_size' => $newFile->getSize(),
            'checksum' => hash_file('sha256', $newPath),
            'current_version' => $file['current_version'] + 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $fileId]);

        // Alte Versionen aufräumen
        $this->cleanupOldVersions($fileId, $file['max_versions']);

        return $this->db->fetchAssociative('SELECT * FROM storage_files WHERE id = ?', [$fileId]);
    }

    private function createVersion(array $file): void
    {
        // Datei kopieren
        $versionPath = $this->getVersionPath($file['id'], $file['current_version']);
        copy($file['file_path'], $versionPath);

        $this->db->insert('storage_file_versions', [
            'id' => Uuid::uuid4()->toString(),
            'file_id' => $file['id'],
            'version_number' => $file['current_version'],
            'file_path' => $versionPath,
            'file_size' => $file['file_size'],
            'checksum' => $file['checksum'],
            'created_by' => $file['user_id'],
        ]);
    }

    public function restoreVersion(string $fileId, int $versionNumber, string $userId): array
    {
        $version = $this->db->fetchAssociative(
            'SELECT * FROM storage_file_versions WHERE file_id = ? AND version_number = ?',
            [$fileId, $versionNumber]
        );

        if (!$version) {
            throw new NotFoundException('Version not found');
        }

        $file = $this->db->fetchAssociative('SELECT * FROM storage_files WHERE id = ?', [$fileId]);

        // Aktuelle als Version sichern
        $this->createVersion($file);

        // Version wiederherstellen
        copy($version['file_path'], $file['file_path']);

        $this->db->update('storage_files', [
            'file_size' => $version['file_size'],
            'checksum' => $version['checksum'],
            'current_version' => $file['current_version'] + 1,
        ], ['id' => $fileId]);

        return $this->db->fetchAssociative('SELECT * FROM storage_files WHERE id = ?', [$fileId]);
    }
}
```

### API Routes

```php
GET    /api/v1/storage/{id}/versions        // Liste aller Versionen
GET    /api/v1/storage/{id}/versions/{v}    // Version herunterladen
POST   /api/v1/storage/{id}/versions/{v}/restore  // Version wiederherstellen
DELETE /api/v1/storage/{id}/versions/{v}    // Version löschen
```

---

## Feature 11: Audit-Log UI

**Status:** Neu
**Priorität:** Niedrig
**Aufwand:** Klein (1-2 Tage)

### Beschreibung
Vollständige UI zum Durchsuchen und Filtern der Audit-Logs.

### Backend API (erweitern)

```php
// Route bereits vorhanden, Response erweitern
GET /api/v1/system/audit-logs?
    user_id=xxx&
    action=LOGIN,LOGOUT&
    date_from=2024-01-01&
    date_to=2024-01-31&
    ip=192.168.&
    page=1&
    per_page=50
```

```php
public function getAuditLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    $params = $request->getQueryParams();

    $where = ['1=1'];
    $bindings = [];

    if (!empty($params['user_id'])) {
        $where[] = 'al.user_id = ?';
        $bindings[] = $params['user_id'];
    }

    if (!empty($params['action'])) {
        $actions = explode(',', $params['action']);
        $placeholders = implode(',', array_fill(0, count($actions), '?'));
        $where[] = "al.action IN ({$placeholders})";
        $bindings = array_merge($bindings, $actions);
    }

    if (!empty($params['date_from'])) {
        $where[] = 'al.created_at >= ?';
        $bindings[] = $params['date_from'] . ' 00:00:00';
    }

    if (!empty($params['date_to'])) {
        $where[] = 'al.created_at <= ?';
        $bindings[] = $params['date_to'] . ' 23:59:59';
    }

    if (!empty($params['ip'])) {
        $where[] = 'al.ip_address LIKE ?';
        $bindings[] = $params['ip'] . '%';
    }

    $sql = 'SELECT al.*, u.username, u.email
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?';

    // Pagination...

    return JsonResponse::paginated($logs, $total, $page, $perPage);
}
```

### Frontend

```vue
<!-- AuditLogsView.vue -->
<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold text-white mb-6">Audit Logs</h1>

    <!-- Filter -->
    <div class="bg-dark-800 p-4 rounded-lg mb-6 grid grid-cols-4 gap-4">
      <select v-model="filters.user_id" class="input">
        <option value="">Alle Benutzer</option>
        <option v-for="user in users" :key="user.id" :value="user.id">
          {{ user.username }}
        </option>
      </select>

      <select v-model="filters.action" class="input">
        <option value="">Alle Aktionen</option>
        <option value="LOGIN">Login</option>
        <option value="LOGOUT">Logout</option>
        <option value="2FA_ENABLED">2FA aktiviert</option>
        <option value="PASSWORD_CHANGED">Passwort geändert</option>
      </select>

      <input type="date" v-model="filters.date_from" class="input" />
      <input type="date" v-model="filters.date_to" class="input" />

      <button @click="loadLogs" class="btn-primary col-span-4">Filter anwenden</button>
    </div>

    <!-- Log-Tabelle -->
    <div class="bg-dark-800 rounded-lg overflow-hidden">
      <table class="w-full">
        <thead class="bg-dark-700">
          <tr>
            <th class="px-4 py-3 text-left text-gray-400">Zeitpunkt</th>
            <th class="px-4 py-3 text-left text-gray-400">Benutzer</th>
            <th class="px-4 py-3 text-left text-gray-400">Aktion</th>
            <th class="px-4 py-3 text-left text-gray-400">Details</th>
            <th class="px-4 py-3 text-left text-gray-400">IP</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id" class="border-t border-dark-700">
            <td class="px-4 py-3 text-white">{{ formatDate(log.created_at) }}</td>
            <td class="px-4 py-3 text-white">{{ log.username || 'System' }}</td>
            <td class="px-4 py-3">
              <span :class="getActionClass(log.action)" class="px-2 py-1 rounded text-xs">
                {{ log.action }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-400">{{ log.description }}</td>
            <td class="px-4 py-3 text-gray-400 font-mono text-sm">{{ log.ip_address }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <Pagination v-model="page" :total="total" :per-page="perPage" @change="loadLogs" />
  </div>
</template>
```

---

## Feature 12: Keyboard Shortcuts

**Status:** Neu
**Priorität:** Mittel
**Aufwand:** Klein (1-2 Tage)

### Beschreibung
Globale Tastaturkürzel für Power-User zur schnellen Navigation und Aktionen.

### Implementation

```javascript
// /frontend/src/composables/useKeyboardShortcuts.js
import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'

export function useKeyboardShortcuts() {
  const router = useRouter()

  const shortcuts = {
    // Navigation
    'g h': () => router.push('/'),                    // Go Home (Dashboard)
    'g l': () => router.push('/lists'),               // Go Lists
    'g d': () => router.push('/documents'),           // Go Documents
    'g k': () => router.push('/kanban'),              // Go Kanban
    'g p': () => router.push('/projects'),            // Go Projects
    'g t': () => router.push('/time'),                // Go Time
    'g s': () => router.push('/settings'),            // Go Settings

    // Actions
    'ctrl+k': () => openGlobalSearch(),               // Globale Suche
    'ctrl+n': () => openQuickCreate(),                // Neues Item
    'ctrl+s': () => saveCurrentItem(),                // Speichern
    'escape': () => closeModals(),                    // Modals schließen

    // Quick Actions
    'n l': () => createNewList(),                     // New List
    'n d': () => createNewDocument(),                 // New Document
    'n t': () => startTimeTracking(),                 // New Time Entry
  }

  let keySequence = ''
  let keyTimer = null

  function handleKeydown(event) {
    // Ignoriere wenn in Input/Textarea
    if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') {
      if (event.key === 'Escape') {
        event.target.blur()
      }
      return
    }

    // Ctrl/Cmd Shortcuts
    if (event.ctrlKey || event.metaKey) {
      const key = `ctrl+${event.key.toLowerCase()}`
      if (shortcuts[key]) {
        event.preventDefault()
        shortcuts[key]()
        return
      }
    }

    // Sequenz-Shortcuts (g h, n l, etc.)
    clearTimeout(keyTimer)
    keySequence += event.key.toLowerCase() + ' '

    keyTimer = setTimeout(() => {
      const trimmed = keySequence.trim()
      if (shortcuts[trimmed]) {
        shortcuts[trimmed]()
      }
      keySequence = ''
    }, 500)
  }

  onMounted(() => {
    document.addEventListener('keydown', handleKeydown)
  })

  onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown)
  })
}
```

### App.vue Integration

```vue
<!-- App.vue -->
<script setup>
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts'

// Globale Shortcuts aktivieren
useKeyboardShortcuts()
</script>
```

### Shortcut-Hilfe Modal

```vue
<!-- ShortcutsHelpModal.vue (öffnen mit ?) -->
<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-dark-800 p-6 rounded-lg max-w-2xl w-full max-h-[80vh] overflow-y-auto">
      <h2 class="text-xl font-bold text-white mb-4">Tastaturkürzel</h2>

      <div class="grid grid-cols-2 gap-6">
        <div>
          <h3 class="text-sm font-semibold text-gray-400 mb-2">Navigation</h3>
          <div class="space-y-2">
            <ShortcutRow keys="g h" description="Dashboard" />
            <ShortcutRow keys="g l" description="Listen" />
            <ShortcutRow keys="g d" description="Dokumente" />
            <ShortcutRow keys="g k" description="Kanban" />
            <ShortcutRow keys="g p" description="Projekte" />
            <ShortcutRow keys="g t" description="Zeiterfassung" />
            <ShortcutRow keys="g s" description="Einstellungen" />
          </div>
        </div>

        <div>
          <h3 class="text-sm font-semibold text-gray-400 mb-2">Aktionen</h3>
          <div class="space-y-2">
            <ShortcutRow keys="Ctrl+K" description="Globale Suche" />
            <ShortcutRow keys="Ctrl+N" description="Neues Item" />
            <ShortcutRow keys="Ctrl+S" description="Speichern" />
            <ShortcutRow keys="Escape" description="Schließen" />
            <ShortcutRow keys="n l" description="Neue Liste" />
            <ShortcutRow keys="n d" description="Neues Dokument" />
            <ShortcutRow keys="n t" description="Zeit starten" />
          </div>
        </div>
      </div>

      <p class="text-xs text-gray-500 mt-4">
        Drücke <kbd class="bg-dark-700 px-1 rounded">?</kbd> um diese Hilfe zu öffnen
      </p>
    </div>
  </div>
</template>
```

---

## Feature 13: Mehrsprachigkeit (i18n)

**Status:** Neu
**Priorität:** Niedrig
**Aufwand:** Groß (5-7 Tage)

### Beschreibung
Vollständige Internationalisierung mit Deutsch und Englisch als Startsprachen.

### Implementation

**Vue-i18n Setup:**
```javascript
// /frontend/src/i18n/index.js
import { createI18n } from 'vue-i18n'
import de from './locales/de.json'
import en from './locales/en.json'

export const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('locale') || 'de',
  fallbackLocale: 'de',
  messages: { de, en },
})
```

**Locale-Dateien:**
```json
// /frontend/src/i18n/locales/de.json
{
  "common": {
    "save": "Speichern",
    "cancel": "Abbrechen",
    "delete": "Löschen",
    "edit": "Bearbeiten",
    "create": "Erstellen",
    "search": "Suchen...",
    "loading": "Laden...",
    "noData": "Keine Daten vorhanden"
  },
  "nav": {
    "dashboard": "Dashboard",
    "lists": "Listen",
    "documents": "Dokumente",
    "kanban": "Kanban",
    "projects": "Projekte",
    "settings": "Einstellungen"
  },
  "lists": {
    "title": "Listen",
    "newList": "Neue Liste",
    "itemsCount": "{count} Einträge",
    "completed": "{count} erledigt"
  },
  "auth": {
    "login": "Anmelden",
    "logout": "Abmelden",
    "email": "E-Mail",
    "password": "Passwort",
    "forgotPassword": "Passwort vergessen?"
  }
}
```

```json
// /frontend/src/i18n/locales/en.json
{
  "common": {
    "save": "Save",
    "cancel": "Cancel",
    "delete": "Delete",
    "edit": "Edit",
    "create": "Create",
    "search": "Search...",
    "loading": "Loading...",
    "noData": "No data available"
  },
  "nav": {
    "dashboard": "Dashboard",
    "lists": "Lists",
    "documents": "Documents",
    "kanban": "Kanban",
    "projects": "Projects",
    "settings": "Settings"
  }
}
```

**Verwendung in Komponenten:**
```vue
<template>
  <div>
    <h1>{{ t('lists.title') }}</h1>
    <button>{{ t('lists.newList') }}</button>
    <p>{{ t('lists.itemsCount', { count: items.length }) }}</p>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
```

**Sprachauswahl in Settings:**
```vue
<!-- SettingsView.vue -->
<div class="flex items-center justify-between py-3">
  <span class="text-white">{{ t('settings.language') }}</span>
  <select v-model="locale" @change="changeLocale" class="input w-32">
    <option value="de">Deutsch</option>
    <option value="en">English</option>
  </select>
</div>

<script setup>
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()

function changeLocale(event) {
  localStorage.setItem('locale', event.target.value)
}
</script>
```

### Backend i18n (für Fehlermeldungen)

```php
// /backend/src/Core/Services/TranslationService.php
class TranslationService
{
    private array $translations = [];
    private string $locale = 'de';

    public function __construct()
    {
        $this->loadTranslations();
    }

    public function setLocale(string $locale): void
    {
        $this->locale = in_array($locale, ['de', 'en']) ? $locale : 'de';
    }

    public function get(string $key, array $params = []): string
    {
        $text = $this->translations[$this->locale][$key] ?? $key;

        foreach ($params as $param => $value) {
            $text = str_replace(":{$param}", $value, $text);
        }

        return $text;
    }
}

// Verwendung in Exceptions
throw new ValidationException($translator->get('validation.required', ['field' => 'title']));
```

---

## Implementierungs-Reihenfolge (Empfehlung)

| Phase | Features | Aufwand |
|-------|----------|---------|
| **1** | API Keys, Keyboard Shortcuts, Favoriten | 3-4 Tage |
| **2** | Password Manager, Export/Import | 5-7 Tage |
| **3** | Globale Tags, Vorlagen-System | 4-5 Tage |
| **4** | Recurring Tasks, Audit-Log UI | 4-5 Tage |
| **5** | Dashboard-Erweiterungen, Datei-Versionierung | 4-5 Tage |
| **6** | Benachrichtigungs-Kanäle | 3-4 Tage |
| **7** | Mehrsprachigkeit (i18n) | 5-7 Tage |

**Gesamtaufwand:** ~30-40 Tage
