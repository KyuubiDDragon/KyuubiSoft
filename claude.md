# KyuubiSoft - Claude AI Assistant Guidelines

This document contains important information for AI assistants working on this codebase.

## Project Overview

KyuubiSoft is a self-hosted productivity and development platform with features including:
- Document management
- Kanban boards
- Calendar with external calendar sync
- Docker container management
- Server monitoring
- Uptime monitoring
- Invoice management
- Ticket system
- API tester
- And more...

## Architecture

- **Backend**: PHP 8.2+ with Slim Framework
- **Frontend**: Vue.js 3 with Pinia stores
- **Database**: MySQL/MariaDB
- **Cache**: Redis

## Feature Flag System

### Overview

The system uses a two-layer access control:

1. **Instance-Level (ENV)**: What features exist on this instance?
2. **User-Level (Permissions)**: What can this user access within instance limits?

### Adding a New Feature

When adding a new feature that should be controllable via feature flags:

#### 1. Backend: Update FeatureService.php

Add the feature definition to the `FEATURES` constant in `/backend/src/Core/Services/FeatureService.php`:

```php
'my_feature' => [
    'modes' => ['disabled', 'limited', 'full'],  // Available modes
    'default' => 'full',                          // Default mode
    'env' => 'FEATURE_MY_FEATURE',               // Environment variable name
    'description' => 'My feature description',
    'subFeatures' => [
        'view' => ['limited', 'full'],           // Which modes allow this sub-feature
        'manage' => ['full'],
        'special_action' => ['full'],
    ],
],
```

#### 2. Backend: Create Permissions Migration

Add permissions to a new migration in `/backend/database/migrations/`:

```sql
INSERT INTO permissions (name, description, module) VALUES
('my_feature.view', 'View my feature', 'my_feature'),
('my_feature.manage', 'Manage my feature', 'my_feature'),
('my_feature.special_action', 'Special action', 'my_feature')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign to roles
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'owner' AND p.module = 'my_feature'
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;
```

#### 3. Backend: Protect Routes with FeatureMiddleware

In `/backend/src/Core/Router.php`:

```php
$protected->get('/my-feature', [MyController::class, 'index'])
    ->add(new FeatureMiddleware('my_feature', null, 'view'));

$protected->post('/my-feature', [MyController::class, 'create'])
    ->add(new FeatureMiddleware('my_feature', null, 'manage'));
```

Middleware parameters:
- `$feature`: Feature name (e.g., 'my_feature')
- `$requiredMode`: Specific mode required (e.g., 'full') or null
- `$subFeature`: Sub-feature to check (e.g., 'manage') or null
- `$checkPermission`: Whether to also check user permission (default: true)

#### 4. Frontend: Update features.js Store

Add sub-feature definitions to `/frontend/src/stores/features.js`:

```javascript
const subFeatures = {
  // ... existing features ...
  my_feature: {
    view: ['limited', 'full'],
    manage: ['full'],
    special_action: ['full'],
  },
}
```

#### 5. Frontend: Add Navigation Item

In `/frontend/src/layouts/components/Sidebar.vue`, add to `allNavigationGroups`:

```javascript
{
  id: 'my_feature',
  name: 'My Feature',
  icon: SomeIcon,
  feature: 'my_feature',  // This enables feature + permission check
  children: [
    { name: 'Overview', href: '/my-feature', icon: ViewIcon },
    { name: 'Settings', href: '/my-feature/settings', icon: CogIcon,
      featurePermission: 'my_feature.manage' },  // Optional: specific permission
  ],
}
```

### Feature Modes Reference

| Feature | Modes | Description |
|---------|-------|-------------|
| `docker` | `disabled`, `portainer_only`, `own`, `full` | Docker management |
| `server` | `disabled`, `own`, `full` | Server monitoring |
| `tools` | `disabled`, `limited`, `full` | Network tools |
| `uptime` | `disabled`, `full` | Uptime monitoring |
| `invoices` | `disabled`, `full` | Invoice management |
| `tickets` | `disabled`, `full` | Ticket system |
| `api_tester` | `disabled`, `limited`, `full` | API tester |
| `youtube` | `disabled`, `full` | YouTube downloader |
| `passwords` | `disabled`, `full` | Password manager |

### Environment Variables

```env
# Full access (default)
FEATURE_DOCKER=full
FEATURE_SERVER=full
FEATURE_TOOLS=full

# Limited access
FEATURE_DOCKER=own           # Own hosts only, no system socket
FEATURE_DOCKER=portainer_only # Only Portainer connections
FEATURE_SERVER=own           # Own servers via SSH, no localhost
FEATURE_TOOLS=limited        # Safe tools only, no SSH

# Disabled
FEATURE_YOUTUBE=disabled     # Completely hidden
```

## Code Style Guidelines

### Backend (PHP)

- Use strict types: `declare(strict_types=1);`
- Follow PSR-12 coding standards
- Use typed properties and return types
- Controllers should be thin, business logic in Services
- Use Doctrine DBAL for database access

### Frontend (Vue.js)

- Use Composition API with `<script setup>`
- Use Pinia for state management
- Use Tailwind CSS for styling
- Components should be single-responsibility

## Testing

Before committing, ensure:
1. No PHP syntax errors
2. No Vue build errors
3. New features have appropriate permissions
4. New routes have appropriate middleware

## Common Patterns

### Adding a new CRUD module

1. Create Controller in `/backend/src/Modules/{Module}/Controllers/`
2. Add routes in `/backend/src/Core/Router.php`
3. Create Vue views in `/frontend/src/modules/{module}/views/`
4. Add to router in `/frontend/src/router/index.js`
5. Add navigation in Sidebar.vue
6. If controllable: Add feature flags as described above
