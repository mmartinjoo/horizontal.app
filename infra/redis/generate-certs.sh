#!/bin/bash
mkdir -p certs
cd certs

openssl genrsa -out redis.key 2048

openssl req -new -x509 -days 3650 -key redis.key -out redis.crt \
-subj "/C=US/ST=State/L=City/O=Organization/CN=redis"

# Set permissions for Redis user (UID 999)
chown 999:999 redis.key redis.crt
chmod 600 redis.key
chmod 644 redis.crt

echo "Redis TLS certificates generated successfully in certs/"