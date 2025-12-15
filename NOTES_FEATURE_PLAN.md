# Notes Feature - Implementierungsplan

> Eine Notion/OneNote-ähnliche Notiz-Funktion für KyuubiSoft

---

## Inhaltsverzeichnis

1. [Übersicht](#übersicht)
2. [Phase 1: Basis-System](#phase-1-basis-system)
3. [Phase 2: Datenbanken](#phase-2-datenbanken)
4. [Phase 3: Collaboration](#phase-3-collaboration)
5. [Phase 4: Integrationen](#phase-4-integrationen)
6. [Technische Details](#technische-details)
7. [Implementierungs-Roadmap](#implementierungs-roadmap)

---

## Übersicht

Das Notes-Modul erweitert KyuubiSoft um ein vollwertiges Notion/OneNote-ähnliches System mit:

| Phase | Hauptfeatures |
|-------|---------------|
| **Phase 1** | Hierarchische Notizen, Wiki-Links, Templates, Slash Commands |
| **Phase 2** | Inline-Datenbanken, Properties, Views (Table/Board/Calendar) |
| **Phase 3** | Echtzeit-Collaboration, Kommentare, @Mentions, Sharing |
| **Phase 4** | Embeds, Web Clipper, Public Pages, Import/Export |

### Abgrenzung zu bestehenden Modulen

| Modul | Zweck | Unterschied zu Notes |
|-------|-------|---------------------|
| **Documents** | Formelle Dokumente mit Versionierung, Public Links | Notes = persönlicher, Wiki-artig, mit Datenbanken |
| **QuickNotes** | Schnelle Sticky-Notes (Plain Text) | Notes = strukturierter, hierarchisch, Rich-Text |
| **Kanban** | Task-Management in Board-Form | Notes-DBs = flexibler, eingebettet in Notizen |

---

# Phase 1: Basis-System

> Hierarchische Notizen mit Wiki-Links, Templates und Rich-Text Editor

## 1.1 Datenbankstruktur

```sql
-- =====================================================
-- PHASE 1: KERN-TABELLEN
-- =====================================================

-- Haupt-Tabelle für Notizen
CREATE TABLE notes (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    parent_id CHAR(36) NULL,              -- Hierarchische Struktur
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,           -- Für Wiki-Links: "meine-notiz"
    content LONGTEXT,                     -- HTML/JSON Content (Tiptap)
    icon VARCHAR(50) DEFAULT NULL,        -- Emoji oder Icon-Name
    cover_image VARCHAR(500) DEFAULT NULL,-- Header-Bild URL
    is_pinned BOOLEAN DEFAULT FALSE,
    is_archived BOOLEAN DEFAULT FALSE,
    is_template BOOLEAN DEFAULT FALSE,
    is_deleted BOOLEAN DEFAULT FALSE,     -- Soft-Delete für Papierkorb
    deleted_at TIMESTAMP NULL,            -- Wann gelöscht (30 Tage Aufbewahrung)
    sort_order INT DEFAULT 0,
    word_count INT DEFAULT 0,             -- Für Statistiken
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES notes(id) ON DELETE SET NULL,

    INDEX idx_notes_user (user_id),
    INDEX idx_notes_parent (parent_id),
    INDEX idx_notes_slug (user_id, slug),
    INDEX idx_notes_pinned (user_id, is_pinned),
    INDEX idx_notes_deleted (user_id, is_deleted, deleted_at),
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
    source_note_id CHAR(36) NOT NULL,
    target_note_id CHAR(36) NOT NULL,
    link_text VARCHAR(255),               -- Der angezeigte Text
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (source_note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (target_note_id) REFERENCES notes(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_note_links_unique (source_note_id, target_note_id),
    INDEX idx_note_links_target (target_note_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Favoriten
CREATE TABLE note_favorites (
    user_id CHAR(36) NOT NULL,
    note_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, note_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kürzlich bearbeitet
CREATE TABLE note_recent (
    user_id CHAR(36) NOT NULL,
    note_id CHAR(36) NOT NULL,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, note_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    INDEX idx_note_recent_time (user_id, accessed_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notiz-Versionen (History)
CREATE TABLE note_versions (
    id CHAR(36) PRIMARY KEY,
    note_id CHAR(36) NOT NULL,
    content LONGTEXT NOT NULL,
    title VARCHAR(255) NOT NULL,
    version_number INT NOT NULL,
    change_summary VARCHAR(500),
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_note_versions (note_id, version_number DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 1.2 Backend-Architektur

```
backend/src/Modules/Notes/
├── Controllers/
│   ├── NoteController.php           # CRUD + Hierarchie
│   ├── NoteTemplateController.php   # Template-Verwaltung
│   └── NoteVersionController.php    # Versionshistorie
├── Services/
│   ├── NoteService.php              # Business Logic
│   ├── WikiLinkService.php          # Wiki-Links parsen & tracken
│   └── NoteSearchService.php        # Volltextsuche
└── routes.php
```

## 1.3 API-Endpunkte (Phase 1)

```php
// ===== CRUD =====
GET    /api/v1/notes                      // Liste (mit ?parent_id, ?archived, ?template Filter)
POST   /api/v1/notes                      // Erstellen
GET    /api/v1/notes/{id}                 // Einzelne Notiz
PUT    /api/v1/notes/{id}                 // Aktualisieren
DELETE /api/v1/notes/{id}                 // Soft-Delete (Papierkorb)

// ===== Hierarchie & Navigation =====
GET    /api/v1/notes/tree                 // Kompletter Baum für Sidebar
GET    /api/v1/notes/{id}/children        // Kind-Notizen
PUT    /api/v1/notes/{id}/move            // Parent ändern
PUT    /api/v1/notes/reorder              // Sortierung ändern
GET    /api/v1/notes/{id}/breadcrumb      // Pfad zur Wurzel

// ===== Schnellzugriff =====
GET    /api/v1/notes/recent               // Kürzlich bearbeitet
GET    /api/v1/notes/favorites            // Favoriten
POST   /api/v1/notes/{id}/favorite        // Favorisieren
DELETE /api/v1/notes/{id}/favorite        // Entfavorisieren
POST   /api/v1/notes/{id}/pin             // Anpinnen
DELETE /api/v1/notes/{id}/pin             // Entpinnen

// ===== Wiki-Links =====
GET    /api/v1/notes/{id}/backlinks       // Wer verlinkt hierher?
GET    /api/v1/notes/by-slug/{slug}       // Für Wiki-Link Auflösung
GET    /api/v1/notes/search               // Volltextsuche (?q=suchbegriff)
GET    /api/v1/notes/search/suggestions   // Autocomplete für [[Links]]

// ===== Templates =====
GET    /api/v1/notes/templates            // Alle Vorlagen
POST   /api/v1/notes/from-template/{id}   // Aus Vorlage erstellen
POST   /api/v1/notes/{id}/make-template   // Als Vorlage speichern

// ===== Versionen =====
GET    /api/v1/notes/{id}/versions        // Versionshistorie
GET    /api/v1/notes/{id}/versions/{vid}  // Bestimmte Version
POST   /api/v1/notes/{id}/versions/{vid}/restore // Wiederherstellen

// ===== Papierkorb =====
GET    /api/v1/notes/trash                // Gelöschte Notizen
POST   /api/v1/notes/{id}/restore         // Wiederherstellen
DELETE /api/v1/notes/{id}/permanent       // Endgültig löschen
DELETE /api/v1/notes/trash/empty          // Papierkorb leeren

// ===== Tags =====
GET    /api/v1/notes/{id}/tags
POST   /api/v1/notes/{id}/tags
DELETE /api/v1/notes/{id}/tags/{tagId}

// ===== Sonstiges =====
POST   /api/v1/notes/{id}/duplicate       // Duplizieren
GET    /api/v1/notes/stats                // Statistiken (Anzahl, Wörter, etc.)
```

## 1.4 Frontend-Komponenten (Phase 1)

```
frontend/src/modules/notes/
├── views/
│   ├── NotesView.vue                # Haupt-View (3-Panel Layout)
│   └── NoteTrashView.vue            # Papierkorb
├── components/
│   ├── sidebar/
│   │   ├── NotesSidebar.vue         # Linke Sidebar
│   │   ├── NoteTreeItem.vue         # Rekursiver Baum-Knoten
│   │   ├── NoteTreeDraggable.vue    # Drag & Drop Wrapper
│   │   └── SidebarSection.vue       # Favoriten/Pinned/Recent Sektion
│   ├── editor/
│   │   ├── NoteEditor.vue           # Editor-Wrapper
│   │   ├── NoteHeader.vue           # Titel + Icon + Cover
│   │   ├── NoteBreadcrumb.vue       # Pfad-Navigation
│   │   └── NoteBacklinks.vue        # Backlinks Panel
│   ├── modals/
│   │   ├── NoteTemplateModal.vue    # Template-Auswahl
│   │   ├── NoteVersionModal.vue     # Versionshistorie
│   │   ├── NoteIconPicker.vue       # Icon/Emoji Auswahl
│   │   └── NoteCoverModal.vue       # Cover-Bild Auswahl
│   └── widgets/
│       ├── NoteQuickSwitcher.vue    # Cmd+K Schnellsuche
│       └── NoteStats.vue            # Wortanzahl etc.
├── stores/
│   ├── notesStore.js                # Haupt-Store
│   └── noteTreeStore.js             # Baum-Zustand
├── composables/
│   ├── useNoteTree.js               # Tree-Logic
│   ├── useNoteKeyboard.js           # Keyboard Shortcuts
│   └── useNoteAutosave.js           # Auto-Save Logic
└── extensions/
    ├── WikiLinkExtension.js         # [[Link]] Extension
    ├── SlashCommandExtension.js     # /command Extension
    ├── CalloutExtension.js          # Info/Warning/Tip Boxen
    └── ToggleExtension.js           # Aufklappbare Blöcke
```

## 1.5 UI-Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ☰  Notes                                    [🔍 Cmd+K]  [⚙️]  [+ Neue Notiz]│
├─────────────────┬───────────────────────────────────────────────────────────┤
│                 │                                                           │
│  🔍 Suchen...   │  📂 Arbeit › 📁 Projekte › 📄 Projekt Alpha    [⭐] [···] │
│                 ├───────────────────────────────────────────────────────────┤
│  ⭐ FAVORITEN   │  ┌─────────────────────────────────────────────────────┐  │
│    📄 Wichtig   │  │ 🎯  Projekt Alpha                           [Cover] │  │
│    📄 Roadmap   │  └─────────────────────────────────────────────────────┘  │
│                 │                                                           │
│  📌 ANGEPINNT   │  # Projektbeschreibung                                    │
│    📄 Quickref  │                                                           │
│                 │  Dies ist das **Hauptprojekt** für Q1 2025.               │
│  🕐 KÜRZLICH    │                                                           │
│    📄 Meeting   │  ## Ziele                                                 │
│    📄 Notizen   │  - [ ] Feature A implementieren                           │
│                 │  - [x] Design Review                                      │
│  ─────────────  │  - [ ] Testing                                            │
│                 │                                                           │
│  📁 ALLE SEITEN │  > 💡 **Tipp:** Siehe auch [[Technische Specs]]           │
│  ▼ 📁 Arbeit    │                                                           │
│    ▼ 📁 Projekte│  ## Ressourcen                                            │
│      📄 Alpha   │  | Name    | Rolle      | Status |                        │
│      📄 Beta    │  |---------|------------|--------|                        │
│    📁 Meetings  │  | Max     | Frontend   | ✅     |                        │
│  ▶ 📁 Privat    │  | Anna    | Backend    | ✅     |                        │
│  📄 Inbox       │                                                           │
│                 │  ```javascript                                            │
│                 │  const config = { env: 'prod' };                          │
│                 │  ```                                                      │
│                 │                                                           │
│                 ├───────────────────────────────────────────────────────────┤
│                 │  🔗 BACKLINKS (2)                                         │
│                 │    📄 Sprint Planning - "...siehe [[Projekt Alpha]]..."   │
│                 │    📄 Roadmap Q1 - "...Hauptfokus auf [[Projekt Alpha]]"  │
├─────────────────┴───────────────────────────────────────────────────────────┤
│  📝 1.247 Wörter  •  Zuletzt bearbeitet: vor 5 Min  •  Gespeichert ✓        │
└─────────────────────────────────────────────────────────────────────────────┘
```

## 1.6 Slash Commands

Eingabe von `/` öffnet Menü:

```
┌─────────────────────────────────────┐
│  BASIC BLOCKS                       │
│  ─────────────────────────────────  │
│  📝 /text      Normaler Text        │
│  H1 /h1        Überschrift 1        │
│  H2 /h2        Überschrift 2        │
│  H3 /h3        Überschrift 3        │
│                                     │
│  LISTEN                             │
│  ─────────────────────────────────  │
│  • /bullet     Aufzählung           │
│  1. /number    Nummeriert           │
│  ☑ /todo       Checkbox-Liste       │
│  ▶ /toggle     Aufklappbar          │
│                                     │
│  ADVANCED                           │
│  ─────────────────────────────────  │
│  " /quote      Zitat                │
│  { } /code     Code-Block           │
│  ═ /divider    Trennlinie           │
│  📊 /table     Tabelle              │
│  💡 /callout   Info-Box             │
│  🔗 /link      Wiki-Link            │
│  🖼 /image     Bild einfügen        │
│  📄 /embed     Notiz einbetten      │
└─────────────────────────────────────┘
```

## 1.7 Templates

Vordefinierte Vorlagen:

| Template | Inhalt |
|----------|--------|
| **Meeting Notes** | Datum, Teilnehmer, Agenda, Diskussion, Action Items |
| **Daily Journal** | Datum, Highlights, Aufgaben, Reflexion |
| **Project Brief** | Übersicht, Ziele, Scope, Timeline, Team |
| **Bug Report** | Titel, Beschreibung, Steps to Reproduce, Expected/Actual |
| **Decision Log** | Kontext, Optionen, Entscheidung, Begründung |
| **Weekly Review** | Achievements, Challenges, Next Week, Notes |
| **1:1 Meeting** | Agenda, Updates, Feedback, Action Items |
| **Brainstorm** | Thema, Ideen-Liste, Kategorien, Next Steps |

---

# Phase 2: Datenbanken

> Das Killer-Feature von Notion: Inline-Datenbanken mit verschiedenen Views

## 2.1 Konzept

Datenbanken in Notes sind strukturierte Tabellen mit:
- **Properties** (Spalten mit Typen wie Text, Number, Select, Date, etc.)
- **Views** (verschiedene Ansichten: Table, Board, Calendar, Gallery, List)
- **Filter & Sort** (dynamische Datenansichten)
- **Relations** (Verknüpfungen zwischen Datenbanken)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  📊 Projekt-Tasks                                    [+ View] [Filter] [⋮]  │
├────────────┬────────────┬────────────┬────────────┬────────────────────────┤
│  [Table ▼] │  Board     │  Calendar  │  Gallery   │                        │
├────────────┴────────────┴────────────┴────────────┴────────────────────────┤
│                                                                             │
│  Name              │ Status      │ Priorität │ Deadline   │ Assigned       │
│  ──────────────────┼─────────────┼───────────┼────────────┼──────────────  │
│  Feature A         │ 🟢 Done     │ High      │ 15.01.2025 │ Max            │
│  Feature B         │ 🟡 Progress │ Medium    │ 20.01.2025 │ Anna           │
│  Bug Fix #123      │ 🔴 Todo     │ Critical  │ 10.01.2025 │ Max            │
│  Documentation     │ 🟡 Progress │ Low       │ 25.01.2025 │ --             │
│  + Neuer Eintrag                                                           │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## 2.2 Datenbankstruktur

```sql
-- =====================================================
-- PHASE 2: DATENBANK-TABELLEN
-- =====================================================

-- Datenbank-Definitionen (eine "Datenbank" ist eine Tabelle-Definition)
CREATE TABLE note_databases (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    note_id CHAR(36) NULL,                -- Wenn inline in einer Notiz
    title VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT NULL,
    is_inline BOOLEAN DEFAULT TRUE,       -- Inline in Notiz oder standalone
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    INDEX idx_note_db_user (user_id),
    INDEX idx_note_db_note (note_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Property-Definitionen (Spalten einer Datenbank)
CREATE TABLE note_database_properties (
    id CHAR(36) PRIMARY KEY,
    database_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM(
        'title',          -- Haupttitel (required, eine pro DB)
        'text',           -- Mehrzeiliger Text
        'number',         -- Zahl mit optionalem Format
        'select',         -- Single-Select Dropdown
        'multi_select',   -- Multi-Select Tags
        'date',           -- Datum oder Datum-Range
        'checkbox',       -- Boolean
        'url',            -- URL mit Preview
        'email',          -- E-Mail
        'phone',          -- Telefonnummer
        'relation',       -- Verknüpfung zu anderer DB
        'rollup',         -- Aggregation über Relation
        'formula',        -- Berechnetes Feld
        'created_time',   -- Auto: Erstelldatum
        'updated_time',   -- Auto: Änderungsdatum
        'created_by',     -- Auto: Ersteller
        'files'           -- Datei-Anhänge
    ) NOT NULL,
    config JSON,                          -- Typ-spezifische Konfiguration
    sort_order INT DEFAULT 0,
    is_visible BOOLEAN DEFAULT TRUE,
    width INT DEFAULT 200,                -- Spaltenbreite in Pixel
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (database_id) REFERENCES note_databases(id) ON DELETE CASCADE,
    INDEX idx_db_props (database_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Select-Optionen für Select/Multi-Select Properties
CREATE TABLE note_database_select_options (
    id CHAR(36) PRIMARY KEY,
    property_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    color VARCHAR(50) DEFAULT 'gray',     -- gray, red, orange, yellow, green, blue, purple, pink
    sort_order INT DEFAULT 0,

    FOREIGN KEY (property_id) REFERENCES note_database_properties(id) ON DELETE CASCADE,
    INDEX idx_select_opts (property_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datenbank-Einträge (Rows)
CREATE TABLE note_database_entries (
    id CHAR(36) PRIMARY KEY,
    database_id CHAR(36) NOT NULL,
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (database_id) REFERENCES note_databases(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_db_entries (database_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Property-Werte für Einträge
CREATE TABLE note_database_values (
    id CHAR(36) PRIMARY KEY,
    entry_id CHAR(36) NOT NULL,
    property_id CHAR(36) NOT NULL,
    value_text TEXT,                      -- Für text, title, url, email, phone
    value_number DECIMAL(20,4),           -- Für number
    value_date DATETIME,                  -- Für date (start)
    value_date_end DATETIME,              -- Für date ranges
    value_boolean BOOLEAN,                -- Für checkbox
    value_json JSON,                      -- Für multi_select, files, formula results
    value_relation CHAR(36),              -- Für relation (entry_id der verknüpften DB)

    FOREIGN KEY (entry_id) REFERENCES note_database_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES note_database_properties(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_db_values_unique (entry_id, property_id),
    INDEX idx_db_values_relation (value_relation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datenbank-Views (verschiedene Ansichten)
CREATE TABLE note_database_views (
    id CHAR(36) PRIMARY KEY,
    database_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('table', 'board', 'calendar', 'gallery', 'list') NOT NULL,
    config JSON NOT NULL,                 -- View-spezifische Config (filter, sort, group, etc.)
    sort_order INT DEFAULT 0,
    is_default BOOLEAN DEFAULT FALSE,

    FOREIGN KEY (database_id) REFERENCES note_databases(id) ON DELETE CASCADE,
    INDEX idx_db_views (database_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 2.3 Property-Typen im Detail

### Config-Schema für jeden Typ:

```javascript
// number
{
  "format": "number" | "currency" | "percent",
  "currency": "EUR" | "USD" | ...,
  "precision": 0-4
}

// select / multi_select
{
  "options": ["option_id_1", "option_id_2", ...]
}

// date
{
  "include_time": true | false,
  "date_format": "DD.MM.YYYY" | "YYYY-MM-DD" | ...,
  "time_format": "24h" | "12h"
}

// relation
{
  "database_id": "uuid",
  "relation_type": "single" | "dual",  // dual = bidirektional
  "reverse_property_id": "uuid"        // Für dual relations
}

// rollup
{
  "relation_property_id": "uuid",
  "target_property_id": "uuid",
  "function": "count" | "sum" | "average" | "min" | "max" | "show_original"
}

// formula
{
  "expression": "prop(\"Price\") * prop(\"Quantity\")",
  "result_type": "number" | "text" | "boolean" | "date"
}
```

## 2.4 View-Konfigurationen

### Table View
```javascript
{
  "visible_properties": ["prop_id_1", "prop_id_2", ...],
  "property_widths": { "prop_id": 200, ... },
  "sort": [
    { "property_id": "uuid", "direction": "asc" | "desc" }
  ],
  "filter": {
    "operator": "and" | "or",
    "conditions": [
      {
        "property_id": "uuid",
        "operator": "equals" | "contains" | "greater_than" | ...,
        "value": "..."
      }
    ]
  }
}
```

### Board View (Kanban)
```javascript
{
  "group_by": "property_id",           // Select-Property für Spalten
  "sub_group_by": "property_id",       // Optional: Zeilen
  "card_preview": ["prop_id_1", ...],  // Angezeigte Properties auf Karte
  "hide_empty_groups": false,
  "sort": [...],
  "filter": {...}
}
```

### Calendar View
```javascript
{
  "date_property": "property_id",      // Welche Date-Property
  "title_property": "property_id",     // Was wird angezeigt
  "color_property": "property_id",     // Optional: Farbe nach Select
  "filter": {...}
}
```

### Gallery View
```javascript
{
  "cover_property": "property_id",     // Files-Property für Bild
  "card_size": "small" | "medium" | "large",
  "fit_image": true | false,
  "preview_properties": [...],
  "sort": [...],
  "filter": {...}
}
```

### List View
```javascript
{
  "show_checkbox": true,
  "preview_properties": [...],
  "sort": [...],
  "filter": {...}
}
```

## 2.5 API-Endpunkte (Phase 2)

```php
// ===== Datenbanken =====
GET    /api/v1/note-databases                           // Alle DBs des Users
POST   /api/v1/note-databases                           // Neue DB erstellen
GET    /api/v1/note-databases/{id}                      // DB mit Properties & Views
PUT    /api/v1/note-databases/{id}                      // DB aktualisieren
DELETE /api/v1/note-databases/{id}                      // DB löschen

// ===== Properties =====
GET    /api/v1/note-databases/{id}/properties           // Alle Properties
POST   /api/v1/note-databases/{id}/properties           // Property hinzufügen
PUT    /api/v1/note-databases/{id}/properties/{pid}     // Property ändern
DELETE /api/v1/note-databases/{id}/properties/{pid}     // Property löschen
PUT    /api/v1/note-databases/{id}/properties/reorder   // Reihenfolge ändern

// ===== Select Options =====
POST   /api/v1/note-database-properties/{pid}/options   // Option hinzufügen
PUT    /api/v1/note-database-options/{oid}              // Option ändern
DELETE /api/v1/note-database-options/{oid}              // Option löschen

// ===== Einträge =====
GET    /api/v1/note-databases/{id}/entries              // Alle Einträge (mit Filter)
POST   /api/v1/note-databases/{id}/entries              // Neuer Eintrag
GET    /api/v1/note-database-entries/{eid}              // Einzelner Eintrag
PUT    /api/v1/note-database-entries/{eid}              // Eintrag aktualisieren
DELETE /api/v1/note-database-entries/{eid}              // Eintrag löschen
PUT    /api/v1/note-databases/{id}/entries/bulk         // Bulk-Update

// ===== Views =====
GET    /api/v1/note-databases/{id}/views                // Alle Views
POST   /api/v1/note-databases/{id}/views                // View erstellen
PUT    /api/v1/note-database-views/{vid}                // View aktualisieren
DELETE /api/v1/note-database-views/{vid}                // View löschen
PUT    /api/v1/note-databases/{id}/views/reorder        // View-Reihenfolge
```

## 2.6 Frontend-Komponenten (Phase 2)

```
frontend/src/modules/notes/components/database/
├── NoteDatabase.vue                 # Hauptkomponente
├── DatabaseHeader.vue               # Titel + View-Tabs + Actions
├── DatabaseToolbar.vue              # Filter, Sort, Search
│
├── views/
│   ├── TableView.vue                # Tabellen-Ansicht
│   ├── BoardView.vue                # Kanban-Board
│   ├── CalendarView.vue             # Kalender-Ansicht
│   ├── GalleryView.vue              # Galerie-Karten
│   └── ListView.vue                 # Einfache Liste
│
├── properties/
│   ├── PropertyCell.vue             # Generischer Cell-Wrapper
│   ├── TextCell.vue
│   ├── NumberCell.vue
│   ├── SelectCell.vue
│   ├── MultiSelectCell.vue
│   ├── DateCell.vue
│   ├── CheckboxCell.vue
│   ├── UrlCell.vue
│   ├── RelationCell.vue
│   ├── FormulaCell.vue
│   └── FilesCell.vue
│
├── modals/
│   ├── PropertyModal.vue            # Property erstellen/bearbeiten
│   ├── ViewModal.vue                # View erstellen/bearbeiten
│   ├── FilterModal.vue              # Filter konfigurieren
│   └── EntryModal.vue               # Eintrag als Modal öffnen
│
└── stores/
    └── databaseStore.js             # Pinia Store für DB-Zustand
```

## 2.7 Tiptap Database Extension

```javascript
// Neue Extension zum Einbetten von Datenbanken
const DatabaseBlock = Node.create({
  name: 'databaseBlock',
  group: 'block',
  atom: true,

  addAttributes() {
    return {
      databaseId: { default: null },
      viewId: { default: null },
    }
  },

  parseHTML() {
    return [{ tag: 'div[data-database-id]' }]
  },

  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, {
      'data-type': 'database-block',
      'data-database-id': HTMLAttributes.databaseId
    })]
  },

  addNodeView() {
    return VueNodeViewRenderer(NoteDatabaseBlock)
  }
})
```

---

# Phase 3: Collaboration

> Echtzeit-Zusammenarbeit, Kommentare und Sharing

## 3.1 Features

| Feature | Beschreibung |
|---------|--------------|
| **Echtzeit-Sync** | Mehrere User bearbeiten gleichzeitig (via Yjs) |
| **Cursor-Anzeige** | Sehen wo andere User gerade sind |
| **Kommentare** | Kommentare auf Absätzen/Blöcken |
| **@Mentions** | User in Notizen erwähnen |
| **Sharing** | Notizen mit anderen teilen (View/Edit) |
| **Activity Log** | Wer hat wann was geändert |

## 3.2 Datenbankstruktur

```sql
-- =====================================================
-- PHASE 3: COLLABORATION-TABELLEN
-- =====================================================

-- Notiz-Shares (Freigaben)
CREATE TABLE note_shares (
    id CHAR(36) PRIMARY KEY,
    note_id CHAR(36) NOT NULL,
    shared_with_user_id CHAR(36) NULL,    -- User-Freigabe
    shared_with_email VARCHAR(255) NULL,   -- E-Mail-Einladung (noch nicht registriert)
    permission ENUM('view', 'comment', 'edit') NOT NULL DEFAULT 'view',
    share_token CHAR(64) NULL,            -- Für Link-Sharing
    token_expires_at TIMESTAMP NULL,
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_note_share_user (note_id, shared_with_user_id),
    INDEX idx_note_share_token (share_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kommentare
CREATE TABLE note_comments (
    id CHAR(36) PRIMARY KEY,
    note_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    parent_id CHAR(36) NULL,              -- Für Thread-Antworten
    block_id VARCHAR(255) NULL,           -- Tiptap Block-ID für Position
    content TEXT NOT NULL,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_by CHAR(36) NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES note_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_note_comments (note_id, block_id),
    INDEX idx_note_comments_thread (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- @Mentions
CREATE TABLE note_mentions (
    id CHAR(36) PRIMARY KEY,
    note_id CHAR(36) NOT NULL,
    mentioned_user_id CHAR(36) NOT NULL,
    mentioned_by CHAR(36) NOT NULL,
    block_id VARCHAR(255),                -- Wo im Dokument
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (mentioned_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mentioned_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_note_mentions (mentioned_user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity Log
CREATE TABLE note_activities (
    id CHAR(36) PRIMARY KEY,
    note_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    action ENUM(
        'created', 'updated', 'deleted', 'restored',
        'shared', 'unshared', 'permission_changed',
        'commented', 'comment_resolved',
        'mentioned', 'moved', 'renamed'
    ) NOT NULL,
    details JSON,                         -- Action-spezifische Details
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_note_activities (note_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Collaboration Sessions (für Cursor-Tracking)
CREATE TABLE note_collab_sessions (
    id CHAR(36) PRIMARY KEY,
    note_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    cursor_position JSON,                 -- {from: x, to: y}
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_collab_session (note_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 3.3 Yjs Integration

Das Collaboration-System nutzt die bestehende Yjs-Infrastruktur:

```javascript
// frontend/src/modules/notes/composables/useNoteCollaboration.js
import * as Y from 'yjs'
import { WebsocketProvider } from 'y-websocket'
import { Collaboration } from '@tiptap/extension-collaboration'
import { CollaborationCursor } from '@tiptap/extension-collaboration-cursor'

export function useNoteCollaboration(noteId, user) {
  const ydoc = new Y.Doc()

  const provider = new WebsocketProvider(
    'wss://your-domain/collaboration',
    `note-${noteId}`,
    ydoc
  )

  // Cursor-Awareness
  provider.awareness.setLocalStateField('user', {
    name: user.name,
    color: user.color,
    avatar: user.avatar
  })

  // Tiptap Extensions
  const collaborationExtensions = [
    Collaboration.configure({
      document: ydoc
    }),
    CollaborationCursor.configure({
      provider,
      user: { name: user.name, color: user.color }
    })
  ]

  return {
    ydoc,
    provider,
    collaborationExtensions
  }
}
```

## 3.4 API-Endpunkte (Phase 3)

```php
// ===== Sharing =====
GET    /api/v1/notes/{id}/shares                   // Alle Freigaben
POST   /api/v1/notes/{id}/shares                   // Freigabe erstellen
PUT    /api/v1/notes/{id}/shares/{sid}             // Permission ändern
DELETE /api/v1/notes/{id}/shares/{sid}             // Freigabe entfernen
GET    /api/v1/notes/shared-with-me                // Mit mir geteilte Notizen

// ===== Link-Sharing =====
POST   /api/v1/notes/{id}/share-link               // Share-Link generieren
DELETE /api/v1/notes/{id}/share-link               // Share-Link deaktivieren
GET    /api/v1/shared/{token}                      // Shared Note abrufen (public)

// ===== Kommentare =====
GET    /api/v1/notes/{id}/comments                 // Alle Kommentare
POST   /api/v1/notes/{id}/comments                 // Kommentar erstellen
PUT    /api/v1/note-comments/{cid}                 // Kommentar bearbeiten
DELETE /api/v1/note-comments/{cid}                 // Kommentar löschen
POST   /api/v1/note-comments/{cid}/resolve         // Als erledigt markieren
POST   /api/v1/note-comments/{cid}/unresolve       // Wieder öffnen

// ===== Mentions =====
GET    /api/v1/notes/mentions                      // Meine Mentions (ungelesen)
POST   /api/v1/note-mentions/{mid}/read            // Als gelesen markieren
GET    /api/v1/users/search?q=                     // User-Suche für @mentions

// ===== Activity =====
GET    /api/v1/notes/{id}/activity                 // Activity Log
GET    /api/v1/notes/activity-feed                 // Globaler Feed

// ===== Collaboration =====
GET    /api/v1/notes/{id}/collaborators            // Wer ist gerade online?
POST   /api/v1/notes/{id}/presence                 // Presence-Update
```

## 3.5 Frontend-Komponenten (Phase 3)

```
frontend/src/modules/notes/components/collaboration/
├── ShareModal.vue                   # Freigabe-Dialog
├── ShareUserList.vue                # Liste der Freigaben
├── ShareLinkSection.vue             # Link-Sharing Sektion
├── CollaboratorAvatars.vue          # Online-User Avatare
├── CommentsSidebar.vue              # Kommentar-Panel
├── CommentThread.vue                # Einzelner Kommentar-Thread
├── CommentInput.vue                 # Kommentar schreiben
├── MentionSuggestion.vue            # @mention Autocomplete
├── ActivityPanel.vue                # Activity Log Ansicht
└── PresenceIndicator.vue            # "X is typing..."
```

## 3.6 UI-Mockup: Kommentare

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  📄 Projektplan                                          [👥 2] [💬 3] [⋮]  │
├─────────────────────────────────────────────────────────┬───────────────────┤
│                                                         │  KOMMENTARE       │
│  # Einleitung                                           │                   │
│                                                         │  ┌─────────────┐  │
│  Dieses Projekt zielt darauf ab, die [Infrastruktur  ●]│  │ 👤 Max      │  │
│  zu modernisieren.                                      │  │ vor 2 Std   │  │
│                                                         │  │             │  │
│  ## Ziele                                               │  │ Was meinst  │  │
│                                                         │  │ du mit      │  │
│  1. Performance verbessern                              │  │ "modern"?   │  │
│  2. Code-Qualität erhöhen                              │  │             │  │
│  3. @Anna bitte Review machen                          │  │ [Antworten] │  │
│                                                         │  └─────────────┘  │
│                                                         │                   │
│  > 💡 Deadline: 15. Januar                              │  ┌─────────────┐  │
│                                                         │  │ ✓ Erledigt  │  │
│                                                         │  │ vor 1 Tag   │  │
│                                                         │  └─────────────┘  │
│                                                         │                   │
│                                                         │  [💬 Kommentar]   │
└─────────────────────────────────────────────────────────┴───────────────────┘
```

---

# Phase 4: Integrationen

> Embeds, Web Clipper, Public Pages und Import/Export

## 4.1 Embeds

Externe Inhalte direkt in Notizen einbetten:

| Embed-Typ | Unterstützt |
|-----------|-------------|
| **Video** | YouTube, Vimeo, Loom |
| **Audio** | Spotify, SoundCloud |
| **Design** | Figma, Miro, Excalidraw |
| **Code** | GitHub Gist, CodePen, CodeSandbox |
| **Docs** | Google Docs/Sheets/Slides |
| **Social** | Twitter/X, LinkedIn Posts |
| **Maps** | Google Maps, OpenStreetMap |
| **Andere** | iFrame (beliebige URL) |

### Datenbankstruktur

```sql
-- =====================================================
-- PHASE 4: INTEGRATION-TABELLEN
-- =====================================================

-- Embed-Registry (für Preview-Metadaten Cache)
CREATE TABLE note_embeds (
    id CHAR(36) PRIMARY KEY,
    url VARCHAR(2000) NOT NULL,
    embed_type VARCHAR(50) NOT NULL,
    title VARCHAR(500),
    description TEXT,
    thumbnail_url VARCHAR(2000),
    embed_html TEXT,                      -- oEmbed HTML
    metadata JSON,                        -- Provider-spezifisch
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_embed_url (url(500)),
    INDEX idx_embed_type (embed_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Web Clips (gespeicherte Webseiten)
CREATE TABLE note_web_clips (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    note_id CHAR(36) NULL,                -- Ziel-Notiz (optional)
    url VARCHAR(2000) NOT NULL,
    title VARCHAR(500),
    content LONGTEXT,                     -- Gespeicherter HTML/Text
    screenshot_path VARCHAR(500),         -- Screenshot der Seite
    clip_type ENUM('full_page', 'selection', 'bookmark') NOT NULL,
    tags JSON,                            -- Auto-erkannte Tags
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE SET NULL,
    INDEX idx_clips_user (user_id),
    FULLTEXT INDEX ft_clips_search (title, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Public Pages
CREATE TABLE note_public_pages (
    id CHAR(36) PRIMARY KEY,
    note_id CHAR(36) NOT NULL,
    slug VARCHAR(255) NOT NULL,           -- custom-url-slug
    is_published BOOLEAN DEFAULT TRUE,
    allow_search_indexing BOOLEAN DEFAULT FALSE,
    custom_domain VARCHAR(255),           -- Optional: eigene Domain
    password_hash VARCHAR(255),           -- Optional: Passwortschutz
    view_count INT DEFAULT 0,
    settings JSON,                        -- Theme, Header, Footer, etc.
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_public_slug (slug),
    INDEX idx_public_domain (custom_domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Import Jobs (für asynchrone Imports)
CREATE TABLE note_import_jobs (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    source_type ENUM('notion', 'evernote', 'markdown', 'html', 'roam') NOT NULL,
    file_path VARCHAR(500),
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    progress INT DEFAULT 0,               -- 0-100
    total_items INT DEFAULT 0,
    imported_items INT DEFAULT 0,
    error_log TEXT,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_import_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 4.2 API-Endpunkte (Phase 4)

```php
// ===== Embeds =====
POST   /api/v1/embeds/preview              // URL-Preview abrufen (oEmbed)
GET    /api/v1/embeds/providers            // Unterstützte Provider

// ===== Web Clipper =====
POST   /api/v1/web-clips                   // Neuen Clip speichern
GET    /api/v1/web-clips                   // Alle Clips
GET    /api/v1/web-clips/{id}              // Einzelner Clip
DELETE /api/v1/web-clips/{id}              // Clip löschen
POST   /api/v1/web-clips/{id}/to-note      // Clip in Notiz umwandeln

// ===== Public Pages =====
POST   /api/v1/notes/{id}/publish          // Als Public Page veröffentlichen
PUT    /api/v1/notes/{id}/public-settings  // Einstellungen ändern
DELETE /api/v1/notes/{id}/unpublish        // Veröffentlichung aufheben
GET    /api/v1/public/{slug}               // Public Page abrufen (ohne Auth)
POST   /api/v1/public/{slug}/verify        // Passwort verifizieren

// ===== Export =====
GET    /api/v1/notes/{id}/export?format=markdown
GET    /api/v1/notes/{id}/export?format=html
GET    /api/v1/notes/{id}/export?format=pdf
GET    /api/v1/notes/{id}/export?format=docx
POST   /api/v1/notes/export-all            // Alle Notizen als ZIP

// ===== Import =====
POST   /api/v1/notes/import                // Import starten
GET    /api/v1/notes/import/{jobId}        // Import-Status
GET    /api/v1/notes/import/preview        // Vorschau vor Import
```

## 4.3 Web Clipper Browser Extension

```
browser-extension/
├── manifest.json
├── popup/
│   ├── popup.html
│   ├── popup.js
│   └── popup.css
├── content/
│   └── content.js              # Page-Scraping
├── background/
│   └── background.js           # API-Kommunikation
└── icons/
    └── ...
```

### Popup UI

```
┌───────────────────────────────┐
│  🦊 KyuubiSoft Clipper        │
├───────────────────────────────┤
│                               │
│  📄 Aktuelle Seite            │
│  "GitHub - KyuubiSoft/..."    │
│                               │
│  ┌─────────────────────────┐  │
│  │ 📋 Ganze Seite          │  │
│  │ 📝 Nur Auswahl          │  │
│  │ 🔖 Als Bookmark         │  │
│  └─────────────────────────┘  │
│                               │
│  Speichern in:                │
│  [📁 Inbox           ▼]       │
│                               │
│  Tags:                        │
│  [development] [github] [+]   │
│                               │
│  [      💾 Speichern      ]   │
│                               │
└───────────────────────────────┘
```

## 4.4 Public Pages

Notizen als öffentliche Webseiten veröffentlichen:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  📄 Projektplan                                     [Publish Settings ⚙️]   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ✅ Veröffentlicht                                                          │
│                                                                             │
│  🔗 Public URL:                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ https://kyuubisoft.app/p/projektplan-q1-2025                [Copy]  │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  Optionen:                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ ☑ Suchmaschinen-Indexierung erlauben                                │   │
│  │ ☐ Passwortschutz aktivieren                                         │   │
│  │ ☑ Unterseiten mit veröffentlichen                                   │   │
│  │ ☐ Kommentare erlauben                                               │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  📊 Statistiken:                                                            │
│  • 1.234 Aufrufe                                                            │
│  • Veröffentlicht am: 01.01.2025                                           │
│                                                                             │
│  [     🚫 Veröffentlichung aufheben     ]                                   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## 4.5 Import/Export Formate

### Export

| Format | Beschreibung |
|--------|--------------|
| **Markdown** | Standard .md mit Frontmatter |
| **HTML** | Standalone HTML mit eingebettetem CSS |
| **PDF** | Druckoptimiert mit Inhaltsverzeichnis |
| **DOCX** | Microsoft Word kompatibel |
| **JSON** | Strukturierter Export (für Backup/Migration) |

### Import

| Quelle | Unterstützt |
|--------|-------------|
| **Notion** | Export-ZIP (Markdown + CSV) |
| **Evernote** | .enex Dateien |
| **Roam Research** | JSON Export |
| **Markdown** | Einzelne Dateien oder Ordner-Struktur |
| **HTML** | Web-Artikel |
| **OneNote** | Via HTML-Export |

## 4.6 Frontend-Komponenten (Phase 4)

```
frontend/src/modules/notes/components/integrations/
├── embeds/
│   ├── EmbedBlock.vue              # Generischer Embed-Container
│   ├── EmbedPicker.vue             # URL eingeben + Preview
│   ├── YouTubeEmbed.vue
│   ├── FigmaEmbed.vue
│   ├── TwitterEmbed.vue
│   ├── GistEmbed.vue
│   └── IframeEmbed.vue             # Fallback für beliebige URLs
│
├── webClipper/
│   ├── WebClipList.vue             # Liste gespeicherter Clips
│   ├── WebClipPreview.vue          # Clip-Vorschau
│   └── WebClipToNote.vue           # Clip → Notiz Konverter
│
├── publicPages/
│   ├── PublishModal.vue            # Veröffentlichungs-Dialog
│   ├── PublicPageSettings.vue      # Einstellungen
│   ├── PublicPageView.vue          # Öffentliche Ansicht
│   └── PublicPageTheme.vue         # Theme-Auswahl
│
└── importExport/
    ├── ExportModal.vue             # Export-Dialog
    ├── ImportModal.vue             # Import-Dialog
    ├── ImportProgress.vue          # Fortschrittsanzeige
    └── ImportPreview.vue           # Vorschau vor Import
```

## 4.7 Tiptap Embed Extension

```javascript
// Embed Node für Tiptap
const EmbedBlock = Node.create({
  name: 'embedBlock',
  group: 'block',
  atom: true,

  addAttributes() {
    return {
      url: { default: '' },
      embedType: { default: 'iframe' },
      aspectRatio: { default: '16:9' },
      title: { default: '' },
    }
  },

  addCommands() {
    return {
      insertEmbed: (url) => ({ commands }) => {
        // URL analysieren und Typ erkennen
        const embedType = detectEmbedType(url)
        return commands.insertContent({
          type: this.name,
          attrs: { url, embedType }
        })
      }
    }
  }
})
```

---

# Technische Details

## Bestehende Infrastruktur nutzen

```javascript
// Bereits vorhanden - wiederverwenden:
import TipTapEditor from '@/components/TipTapEditor.vue'
import { useTagsStore } from '@/modules/tags/stores/tagsStore'
import { useFavoritesStore } from '@/stores/favoritesStore'
import { useSearchStore } from '@/modules/search/stores/searchStore'

// Yjs Collaboration (bereits konfiguriert)
import { WebsocketProvider } from 'y-websocket'
import { Collaboration } from '@tiptap/extension-collaboration'
import { CollaborationCursor } from '@tiptap/extension-collaboration-cursor'
```

## Performance-Optimierungen

| Bereich | Maßnahme |
|---------|----------|
| **Lazy Loading** | Notizen-Inhalt erst bei Bedarf laden |
| **Virtualisierung** | `vue-virtual-scroller` für große Bäume |
| **Debouncing** | Auto-Save mit 500ms Debounce |
| **Caching** | Redis-Cache für häufige Queries |
| **Pagination** | Cursor-basierte Pagination für Listen |
| **Indexing** | FULLTEXT + normale Indizes optimieren |

## Sicherheit

| Bereich | Maßnahme |
|---------|----------|
| **XSS** | Content Sanitization (DOMPurify) |
| **CSRF** | Bestehende Middleware |
| **Auth** | JWT + Permission-Checks |
| **Sharing** | Token-basiert mit Expiration |
| **Public Pages** | Rate Limiting + optional Passwort |

## Feature Flags

```php
// Granulare Feature-Kontrolle
'notes'                    => true,
'notes.databases'          => true,
'notes.collaboration'      => true,
'notes.comments'           => true,
'notes.public_pages'       => true,
'notes.web_clipper'        => true,
'notes.embeds'             => true,
'notes.import'             => true,
'notes.export'             => true,
```

## Berechtigungen

```php
// Rollenbasierte Permissions
'notes.view'               => 'Notizen ansehen',
'notes.create'             => 'Notizen erstellen',
'notes.edit'               => 'Notizen bearbeiten',
'notes.delete'             => 'Notizen löschen',
'notes.share'              => 'Notizen teilen',
'notes.publish'            => 'Notizen veröffentlichen',
'notes.databases.manage'   => 'Datenbanken verwalten',
'notes.import'             => 'Notizen importieren',
'notes.export'             => 'Notizen exportieren',
```

---

# Implementierungs-Roadmap

## Phase 1: Basis-System

| Sprint | Aufgaben |
|--------|----------|
| **1.1** | DB-Migrationen, NoteController CRUD, Basis-Frontend |
| **1.2** | Tree-View, Drag & Drop, Breadcrumbs |
| **1.3** | Wiki-Links, Backlinks, Quick Switcher |
| **1.4** | Slash Commands, Callouts, Toggles |
| **1.5** | Templates, Versionen, Papierkorb |

## Phase 2: Datenbanken

| Sprint | Aufgaben |
|--------|----------|
| **2.1** | DB-Schema, Property-System, Table View |
| **2.2** | Board View (Kanban), Filter & Sort |
| **2.3** | Calendar View, Gallery View |
| **2.4** | Relations, Rollups |
| **2.5** | Formeln, Polish |

## Phase 3: Collaboration

| Sprint | Aufgaben |
|--------|----------|
| **3.1** | Sharing-System, Permissions |
| **3.2** | Yjs Integration, Echtzeit-Sync |
| **3.3** | Kommentare, @Mentions |
| **3.4** | Activity Log, Notifications |

## Phase 4: Integrationen

| Sprint | Aufgaben |
|--------|----------|
| **4.1** | Embed-System, oEmbed |
| **4.2** | Web Clipper Extension |
| **4.3** | Public Pages |
| **4.4** | Import/Export |

---

## Zusammenfassung

Dieses erweiterte Notes-Feature macht KyuubiSoft zu einer vollwertigen Notion-Alternative:

| Phase | Status | Features |
|-------|--------|----------|
| **Phase 1** | Geplant | Hierarchie, Wiki-Links, Templates, Slash Commands |
| **Phase 2** | Geplant | Inline-Datenbanken, Views, Relations |
| **Phase 3** | Geplant | Echtzeit-Collaboration, Kommentare, Sharing |
| **Phase 4** | Geplant | Embeds, Web Clipper, Public Pages, Import/Export |

**Gesamtumfang:** ~17 Sprints über alle Phasen

---

*Plan erstellt am: 2025-12-15*
*Letzte Aktualisierung: 2025-12-15*
