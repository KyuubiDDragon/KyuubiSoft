#!/bin/sh
set -e

# Fix Docker socket permissions if mounted
if [ -S /var/run/docker.sock ]; then
    # Get the GID of the docker socket
    DOCKER_SOCK_GID=$(stat -c '%g' /var/run/docker.sock)

    # Create docker group with that GID if it doesn't exist
    if ! getent group docker > /dev/null 2>&1; then
        addgroup -g "$DOCKER_SOCK_GID" docker
    fi

    # Add www-data to docker group
    addgroup www-data docker 2>/dev/null || true

    echo "Docker socket configured with GID: $DOCKER_SOCK_GID"
fi

# Create storage directories with proper permissions
STORAGE_PATH="${STORAGE_PATH:-/var/www/storage}"
mkdir -p "$STORAGE_PATH/discord/media"
chown -R www-data:www-data "$STORAGE_PATH"
chmod -R 775 "$STORAGE_PATH"
echo "Storage directory configured: $STORAGE_PATH"

# Clear compiled DI container cache (rebuilt automatically on first request)
rm -f /var/www/html/storage/cache/container/CompiledContainer.php

# Run database migrations automatically on startup
echo "Running database migrations..."
php /var/www/html/bin/migrate.php || echo "Migration failed (non-fatal, continuing...)"

# Execute the main command
exec "$@"
