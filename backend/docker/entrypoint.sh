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

# Execute the main command
exec "$@"
