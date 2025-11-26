#!/bin/bash

export MEMGRAPH1_USER=
export MEMGRAPH1_PASSWORD=
export MEMGRAPH2_USER=
export MEMGRAPH2_PASSWORD=

echo "vm.max_map_count=262144" >> /etc/sysctl.d/99-memgraph.conf
sudo sysctl -p /etc/sysctl.d/99-memgraph.conf

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