#!/bin/bash

export REDIS_PASSWORD=

echo "vm.overcommit_memory = 1" > /etc/sysctl.d/99-redis.conf
sudo sysctl -p /etc/sysctl.d/99-redis.conf

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