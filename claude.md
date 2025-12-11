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
