# Notes Feature - Implementierungsplan

> Eine Notion/OneNote-ähnliche Notiz-Funktion für KyuubiSoft

## Übersicht

Das Notes-Modul erweitert KyuubiSoft um ein hierarchisches, Wiki-ähnliches Notizsystem mit folgenden Kernfunktionen:

- **Hierarchische Struktur** - Verschachtelte Seiten (Parent/Child)
- **Rich-Text Editor** - Basierend auf dem bestehenden Tiptap-Editor
- **Wiki-Links** - Verlinkungen zwischen Notizen `[[Seitenname]]`
- **Templates** - Vorlagen für wiederkehrende Notiztypen
- **Tagging & Suche** - Volltextsuche und Tag-basierte Organisation
- **Favoriten & Pinning** - Schnellzugriff auf wichtige Notizen

---

## Abgrenzung zu bestehenden Modulen

| Modul | Zweck | Unterschied zu Notes |
|-------|-------|---------------------|
| **Documents** | Formelle Dokumente mit Versionierung, Sharing, Public Links | Notes = persönlicher, schneller, Wiki-artig |
| **QuickNotes** | Schnelle Sticky-Notes (Plain Text) | Notes = strukturierter, hierarchisch, Rich-Text |

**Notes** füllt die Lücke zwischen dem einfachen QuickNotes-Widget und dem komplexen Documents-Modul.

---

## Phase 1: Basis-Infrastruktur

### 1.1 Datenbankstruktur

```sql
-- Haupt-Tabelle für Notizen
CREATE TABLE notes (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    parent_id CHAR(36) NULL,              -- Für hierarchische Struktur
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,           -- Für Wiki-Links: "meine-notiz"
    content LONGTEXT,                     -- HTML/JSON Content
    icon VARCHAR(50) DEFAULT NULL,        -- Emoji oder Icon-Name
    cover_image VARCHAR(500) DEFAULT NULL, -- Header-Bild URL
    is_pinned BOOLEAN DEFAULT FALSE,
    is_archived BOOLEAN DEFAULT FALSE,
    is_template BOOLEAN DEFAULT FALSE,    -- Als Vorlage markieren
    sort_order INT DEFAULT 0,             -- Sortierung innerhalb Parent
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES notes(id) ON DELETE SET NULL,

    INDEX idx_notes_user (user_id),
    INDEX idx_notes_parent (parent_id),
    INDEX idx_notes_slug (user_id, slug),
    INDEX idx_notes_pinned (user_id, is_pinned),
    FULLTEXT INDEX ft_notes_search (title, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notiz-Tags (Many-to-Many mit bestehendem Tags-System)
CREATE TABLE note_tags (
    note_id CHAR(36) NOT NULL,
    tag_id CHAR(36) NOT NULL,
    PRIMARY KEY (note_id, tag_id),
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Wiki-Links Tracking (für Backlinks)
CREATE TABLE note_links (
    id CHAR(36) PRIMARY KEY,
    source_note_id CHAR(36) NOT NULL,     -- Die Notiz die den Link enthält
    target_note_id CHAR(36) NOT NULL,     -- Die verlinkte Notiz
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (source_note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (target_note_id) REFERENCES notes(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_note_links_unique (source_note_id, target_note_id),
    INDEX idx_note_links_target (target_note_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notiz-Favoriten (User kann Notizen favorisieren)
CREATE TABLE note_favorites (
    user_id CHAR(36) NOT NULL,
    note_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, note_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kürzlich bearbeitete Notizen (für "Recent" Liste)
CREATE TABLE note_recent (
    user_id CHAR(36) NOT NULL,
    note_id CHAR(36) NOT NULL,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, note_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    INDEX idx_note_recent_time (user_id, accessed_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 1.2 Backend-Struktur

```
backend/src/Modules/Notes/
├── Controllers/
│   └── NoteController.php        # Haupt-Controller (CRUD + Spezialfunktionen)
├── Services/
│   └── NoteService.php           # Business Logic (Wiki-Links parsen, etc.)
└── routes.php                    # API-Routen Definition
```

### 1.3 API-Endpunkte

```php
// Basis CRUD
GET    /api/v1/notes                    // Liste aller Notizen (mit Filter)
POST   /api/v1/notes                    // Neue Notiz erstellen
GET    /api/v1/notes/{id}               // Einzelne Notiz abrufen
PUT    /api/v1/notes/{id}               // Notiz aktualisieren
DELETE /api/v1/notes/{id}               // Notiz löschen (soft: archivieren)

// Hierarchie & Navigation
GET    /api/v1/notes/tree               // Kompletter Baum für Sidebar
GET    /api/v1/notes/{id}/children      // Direkte Kind-Notizen
PUT    /api/v1/notes/{id}/move          // Notiz verschieben (Parent ändern)
PUT    /api/v1/notes/reorder            // Sortierung ändern

// Spezialfunktionen
GET    /api/v1/notes/recent             // Kürzlich bearbeitet (max 20)
GET    /api/v1/notes/favorites          // Favorisierte Notizen
POST   /api/v1/notes/{id}/favorite      // Zu Favoriten hinzufügen
DELETE /api/v1/notes/{id}/favorite      // Aus Favoriten entfernen
POST   /api/v1/notes/{id}/pin           // Notiz anpinnen
DELETE /api/v1/notes/{id}/pin           // Pin entfernen
POST   /api/v1/notes/{id}/duplicate     // Notiz duplizieren

// Wiki-Links
GET    /api/v1/notes/{id}/backlinks     // Notizen die auf diese verlinken
GET    /api/v1/notes/search             // Volltextsuche
GET    /api/v1/notes/by-slug/{slug}     // Notiz per Slug finden (für Wiki-Links)

// Templates
GET    /api/v1/notes/templates          // Alle Vorlagen
POST   /api/v1/notes/{id}/from-template // Neue Notiz aus Vorlage

// Tags
GET    /api/v1/notes/{id}/tags          // Tags einer Notiz
POST   /api/v1/notes/{id}/tags          // Tags hinzufügen
DELETE /api/v1/notes/{id}/tags/{tagId}  // Tag entfernen

// Archiv
GET    /api/v1/notes/archived           // Archivierte Notizen
POST   /api/v1/notes/{id}/restore       // Aus Archiv wiederherstellen
DELETE /api/v1/notes/{id}/permanent     // Endgültig löschen
```

---

## Phase 2: Frontend-Komponenten

### 2.1 Modul-Struktur

```
frontend/src/modules/notes/
├── views/
│   └── NotesView.vue             # Haupt-View (3-Panel Layout)
├── components/
│   ├── NotesSidebar.vue          # Linke Sidebar mit Baum
│   ├── NoteEditor.vue            # Haupt-Editor Bereich
│   ├── NoteHeader.vue            # Titel, Icon, Cover
│   ├── NoteBreadcrumb.vue        # Pfad-Navigation
│   ├── NoteTreeItem.vue          # Rekursiver Baum-Knoten
│   ├── NoteBacklinks.vue         # Backlinks Panel
│   ├── NoteTemplateModal.vue     # Template-Auswahl
│   ├── WikiLinkSuggestion.vue    # Autocomplete für [[Links]]
│   └── NoteQuickSwitcher.vue     # Cmd+K Schnellsuche
├── stores/
│   └── notesStore.js             # Pinia Store
├── composables/
│   └── useNoteTree.js            # Tree-Logic Composable
└── index.js                      # Modul-Export
```

### 2.2 Haupt-Layout (NotesView.vue)

```
┌─────────────────────────────────────────────────────────────────────┐
│  ☰ Notes                                           [🔍] [⚙️] [+]    │
├──────────────────┬──────────────────────────────────────────────────┤
│                  │  📂 Parent > 📄 Current Note          [⭐] [📌]  │
│  🔍 Search...    ├──────────────────────────────────────────────────┤
│                  │                                                   │
│  ⭐ FAVORITEN    │  # Notiz Titel                                   │
│    📄 Wichtig    │                                                   │
│    📄 Projekt X  │  Der Inhalt der Notiz mit **Rich Text**          │
│                  │  und [[Wiki-Links]] zu anderen Seiten.           │
│  📌 ANGEPINNT    │                                                   │
│    📄 TODO Liste │  - [ ] Aufgabe 1                                 │
│                  │  - [x] Aufgabe 2                                 │
│  🕐 KÜRZLICH     │                                                   │
│    📄 Meeting    │  > Zitat Block                                   │
│    📄 Ideen      │                                                   │
│                  │  ```javascript                                    │
│  📁 ALLE NOTIZEN │  const code = "example";                         │
│  ├─ 📁 Arbeit    │  ```                                             │
│  │  ├─ 📄 Proj A │                                                   │
│  │  └─ 📄 Proj B │                                                   │
│  ├─ 📁 Privat    ├──────────────────────────────────────────────────┤
│  │  └─ 📄 ...    │  🔗 BACKLINKS                                    │
│  └─ 📄 Sonstiges │    📄 Meeting Notes (erwähnt diese Seite)        │
│                  │    📄 Projektplan (verlinkt hierher)             │
└──────────────────┴──────────────────────────────────────────────────┘
```

### 2.3 Tiptap-Editor Erweiterungen

Zusätzlich zu den bestehenden Extensions:

```javascript
// Neue Extensions für Notes
import { Extension } from '@tiptap/core'
import Suggestion from '@tiptap/suggestion'

// 1. Wiki-Link Extension [[Seitenname]]
const WikiLink = Extension.create({
  name: 'wikiLink',
  // Erkennt [[text]] Pattern
  // Zeigt Autocomplete mit existierenden Notizen
  // Rendert als klickbaren internen Link
})

// 2. Callout/Admonition Blocks
const Callout = Extension.create({
  name: 'callout',
  // Info, Warning, Tip, Danger Boxes
  // Ähnlich wie in Notion
})

// 3. Toggle/Collapsible Blocks
const ToggleBlock = Extension.create({
  name: 'toggleBlock',
  // Aufklappbare Sektionen
})

// 4. Embed Block (für interne Notiz-Embeds)
const NoteEmbed = Extension.create({
  name: 'noteEmbed',
  // Bettet andere Notizen inline ein
})
```

---

## Phase 3: Erweiterte Features

### 3.1 Quick Switcher (Cmd+K / Ctrl+K)

```
┌─────────────────────────────────────────┐
│  🔍 Suche nach Notizen...               │
├─────────────────────────────────────────┤
│  📄 Meeting Notes           vor 2 Std   │
│  📄 Projektplan Q1          vor 1 Tag   │
│  📄 API Dokumentation       vor 3 Tagen │
│  📄 Ideen Sammlung          vor 1 Woche │
└─────────────────────────────────────────┘
```

- Globaler Keyboard Shortcut
- Fuzzy-Search über Titel und Inhalt
- Schnelle Navigation

### 3.2 Templates System

Vordefinierte Vorlagen:
- **Meeting Notes** - Datum, Teilnehmer, Agenda, Action Items
- **Daily Journal** - Tagesstruktur mit Reflexion
- **Project Brief** - Ziele, Scope, Timeline
- **Bug Report** - Beschreibung, Steps, Expected vs Actual
- **Decision Log** - Kontext, Optionen, Entscheidung

### 3.3 Import/Export

```php
// Export-Formate
GET /api/v1/notes/{id}/export?format=markdown
GET /api/v1/notes/{id}/export?format=html
GET /api/v1/notes/{id}/export?format=pdf

// Import
POST /api/v1/notes/import  // Markdown-Dateien hochladen
```

### 3.4 Slash Commands im Editor

Typing `/` zeigt Menü:
```
/h1, /h2, /h3     - Überschriften
/bullet           - Aufzählung
/number           - Nummerierte Liste
/todo             - Checkbox-Liste
/quote            - Zitat
/code             - Code-Block
/table            - Tabelle einfügen
/divider          - Trennlinie
/callout          - Info-Box
/toggle           - Aufklappbar
/link             - Wiki-Link einfügen
/image            - Bild einfügen
/embed            - Notiz einbetten
```

---

## Phase 4: Integration mit KyuubiSoft

### 4.1 Modul-Verknüpfungen

| Integration | Beschreibung |
|-------------|--------------|
| **Tasks/Kanban** | Notiz an Task anhängen, Task in Notiz erwähnen |
| **Projects** | Notizen einem Projekt zuordnen |
| **Calendar** | Meeting-Notizen mit Kalender-Events verknüpfen |
| **Tags** | Bestehendes Tag-System nutzen |
| **Search** | In globaler Suche einbinden |
| **Webhooks** | `note.created`, `note.updated`, `note.deleted` Events |
| **Quick Access** | Notizen in Header-Shortcuts |

### 4.2 Feature Flags

```php
// In feature_flags Tabelle
'notes' => true,           // Modul aktivieren
'notes.templates' => true, // Templates Feature
'notes.backlinks' => true, // Backlinks Feature
'notes.export' => true,    // Export Feature
```

### 4.3 Berechtigungen

```php
// Neue Permissions
'notes.view'    => 'Notizen ansehen',
'notes.create'  => 'Notizen erstellen',
'notes.edit'    => 'Notizen bearbeiten',
'notes.delete'  => 'Notizen löschen',
'notes.export'  => 'Notizen exportieren',
```

---

## Implementierungs-Reihenfolge

### Sprint 1: Basis (Core)
- [ ] Datenbank-Migrationen erstellen
- [ ] Backend Controller mit CRUD
- [ ] Frontend NotesView mit Sidebar
- [ ] Basis-Editor Integration
- [ ] Navigation/Routing

### Sprint 2: Hierarchie & Navigation
- [ ] Tree-View für Sidebar
- [ ] Drag & Drop Sortierung
- [ ] Breadcrumb Navigation
- [ ] Parent/Child Beziehungen
- [ ] Recent Notes Liste

### Sprint 3: Wiki-Features
- [ ] Wiki-Link Extension für Tiptap
- [ ] Autocomplete bei `[[`
- [ ] Backlinks Tracking & Anzeige
- [ ] Quick Switcher (Cmd+K)

### Sprint 4: Erweiterte Features
- [ ] Templates System
- [ ] Slash Commands
- [ ] Callout Blocks
- [ ] Toggle Blocks
- [ ] Favoriten & Pinning

### Sprint 5: Polish & Integration
- [ ] Volltextsuche optimieren
- [ ] Export-Funktionen
- [ ] Keyboard Shortcuts
- [ ] Mobile Responsive
- [ ] Integration mit anderen Modulen

---

## Technische Hinweise

### Wiederverwendbare Komponenten

```javascript
// Bereits vorhanden - wiederverwenden:
import TipTapEditor from '@/components/TipTapEditor.vue'
import { useTagsStore } from '@/modules/tags/stores/tagsStore'
import { useFavoritesStore } from '@/stores/favoritesStore'
```

### Performance-Überlegungen

1. **Lazy Loading** - Notizen nur bei Bedarf laden
2. **Virtualisierung** - Bei großen Bäumen (vue-virtual-scroller)
3. **Debounced Save** - Auto-Save mit 500ms Debounce
4. **Indexed Search** - FULLTEXT Index für schnelle Suche

### Sicherheit

1. **XSS Prevention** - Content sanitization bei Render
2. **CSRF** - Bestehende Middleware nutzen
3. **Authorization** - User kann nur eigene Notizen sehen/bearbeiten

---

## Dateien die erstellt/geändert werden

### Neue Dateien

```
backend/
├── database/migrations/
│   └── XXXX_create_notes_tables.php
├── src/Modules/Notes/
│   ├── Controllers/NoteController.php
│   ├── Services/NoteService.php
│   └── routes.php

frontend/
├── src/modules/notes/
│   ├── views/NotesView.vue
│   ├── components/
│   │   ├── NotesSidebar.vue
│   │   ├── NoteEditor.vue
│   │   ├── NoteHeader.vue
│   │   ├── NoteBreadcrumb.vue
│   │   ├── NoteTreeItem.vue
│   │   ├── NoteBacklinks.vue
│   │   └── NoteQuickSwitcher.vue
│   ├── stores/notesStore.js
│   └── index.js
├── src/components/editor/
│   └── WikiLinkExtension.js
```

### Zu ändernde Dateien

```
backend/
├── src/Router.php                    # Notes-Routen registrieren
├── src/Core/Services/FeatureService.php  # Feature Flag

frontend/
├── src/router/index.js               # Route hinzufügen
├── src/components/Header.vue         # Quick Access Icon
├── src/modules/search/               # Global Search Integration
```

---

## Zusammenfassung

Dieses Notes-Feature würde KyuubiSoft um ein mächtiges, aber benutzerfreundliches Notiz-System erweitern, das:

✅ Die bestehende Infrastruktur optimal nutzt (Tiptap, Tags, Search)
✅ Sich nahtlos in das Design einfügt
✅ Skalierbar und erweiterbar ist
✅ Die Lücke zwischen QuickNotes und Documents schließt

**Geschätzter Aufwand:** 5 Sprints (bei modularer Implementierung)

---

*Plan erstellt am: 2025-12-15*
