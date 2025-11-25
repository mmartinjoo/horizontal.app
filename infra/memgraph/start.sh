#!/bin/bash

export MEMGRAPH1_USER=
export MEMGRAPH1_PASSWORD=
export MEMGRAPH2_USER=
export MEMGRAPH2_PASSWORD=

docker compose down
echo "Starting docker compose..."
docker compose up -d

# Check if the command was successful
if [ $? -eq 0 ]; then
    echo "Docker Compose started successfully!"
    docker compose logs
else
    echo "Failed to start Docker Compose!"
    docker compose logs
    exit 1
fi