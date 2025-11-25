#!/bin/bash

export POSTGRES_DB=
export POSTGRES_USER=
export POSTGRES_PASSWORD=

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