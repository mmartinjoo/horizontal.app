#!/bin/bash
mkdir -p certs
cd certs

openssl genrsa -out server.key 2048

openssl req -new -x509 -days 3650 -key server.key -out server.crt \
   -subj "/C=US/ST=State/L=City/O=Organization/CN=postgres"

chmod 600 server.key
chmod 644 server.crt
chown 999:999 server.key server.crt

echo "SSL certificates generated successfully in certs/"