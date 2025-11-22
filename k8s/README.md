# Kubernetes Deployment Guide

This directory contains Kubernetes manifests for deploying the Horizontal application to a DigitalOcean Kubernetes cluster.

## Architecture Overview

The deployment includes:
- **API Service**: Laravel PHP-FPM application (2 replicas)
- **Nginx**: Web server fronting the API (2 replicas)
- **Workers**: Three types of queue workers
  - `worker-indexing`: 4 replicas for indexing and question queues
  - `worker-question`: 1 replica for question prioritization
  - `worker-default`: 1 replica for default queue
- **GraphBuilder API**: Python API service (2 replicas)
- **GraphBuilder Workers**: Python RQ workers (4 replicas)

## Prerequisites

1. DigitalOcean Kubernetes cluster configured
2. `kubectl` installed and configured to access your cluster
3. Docker images built and pushed to a container registry
4. Managed PostgreSQL instance (external)
5. Managed Redis instance (external)
6. Managed Memgraph instances (external)

## Building and Pushing Docker Images

Before deploying, build and push your Docker images:

```bash
# Build and push API image (production)
cd api
docker build -t your-registry/horizontal-api:latest --target api -f Dockerfile.prod .
docker push your-registry/horizontal-api:latest

# Build and push API Worker image (production)
docker build -t your-registry/horizontal-api-worker:latest --target worker -f Dockerfile.prod .
docker push your-registry/horizontal-api-worker:latest

# Build and push GraphBuilder API image (production)
cd ../graphbuilder
docker build -t your-registry/horizontal-graphbuilder:latest --target api -f Dockerfile.prod .
docker push your-registry/horizontal-graphbuilder:latest

# Build and push GraphBuilder Worker image (production)
docker build -t your-registry/horizontal-graphbuilder-worker:latest --target worker-prod -f Dockerfile.prod .
docker push your-registry/horizontal-graphbuilder-worker:latest

# Build and push Tenant App frontend (production)
cd ../tenant-app
docker build -t your-registry/horizontal-tenant-app:latest -f Dockerfile.prod .
docker push your-registry/horizontal-tenant-app:latest
```

**Development Builds:**
- **API**: Use `Dockerfile` - `docker build -t horizontal-api:dev -f Dockerfile .`
- **GraphBuilder**: Use `Dockerfile` with `--target api` or `--target worker`
- **Tenant App**: Use `Dockerfile` - runs Vite dev server on port 5173

**Production Builds:**
- **API**: Use `Dockerfile.prod` with `--target api` (PHP-FPM) or `--target worker` (Queue workers)
- **GraphBuilder**: Use `Dockerfile.prod` with `--target api` or `--target worker-prod`
- **Tenant App**: Use `Dockerfile.prod` - builds with Vite and serves with nginx

**Key Production Improvements:**
- Multi-stage builds for smaller images
- No dev dependencies (composer --no-dev)
- Optimized autoloader (--classmap-authoritative)
- PHP opcache enabled and configured
- Production PHP-FPM settings
- Assets built and optimized
- Better layer caching

**Note**: Update the image references in the deployment YAML files with your actual registry path.

## Configuration

### 1. Create Secrets

Copy the secrets template and fill in your actual values:

```bash
cp secrets.yaml.template secrets.yaml
```

Edit `secrets.yaml` and replace all placeholder values with your actual:
- Database credentials (managed PostgreSQL)
- Redis URL (managed Redis connection string)
- API keys (Fireworks, Google, Jira, Linear, GitHub)
- Memgraph connection details
- Application secrets

### 2. Update ConfigMap

Edit `app-configmap.yaml` to update:
- `JIRA_REDIRECT_URI`: Replace `https://your-domain.com` with your actual domain
- `LINEAR_REDIRECT_URL`: Replace with your actual domain
- `GITHUB_APP_NAME`: Set your GitHub app name

### 3. Update Image References

Update all deployment files to use your container registry:
- `api-deployment.yaml`
- `workers-deployment.yaml`
- `graphbuilder-api-deployment.yaml`
- `graphbuilder-worker-deployment.yaml`

Replace `your-registry/` with your actual registry (e.g., `registry.digitalocean.com/your-registry/`).

## Deployment Order

Deploy resources in this order to ensure dependencies are met:

```bash
# 1. Deploy ConfigMaps
kubectl apply -f app-configmap.yaml
kubectl apply -f nginx-configmap.yaml

# 2. Deploy Secrets
kubectl apply -f secrets.yaml

# 3. Deploy API
kubectl apply -f api-deployment.yaml

# Wait for API to be ready
kubectl wait --for=condition=ready pod -l app=api --timeout=300s

# 4. Deploy Nginx
kubectl apply -f nginx-deployment.yaml

# 5. Deploy Workers
kubectl apply -f workers-deployment.yaml

# 6. Deploy GraphBuilder services
kubectl apply -f graphbuilder-api-deployment.yaml
kubectl apply -f graphbuilder-worker-deployment.yaml
```

## Quick Deploy (All at Once)

If you're confident in your configuration:

```bash
kubectl apply -f .
```

**Note**: This applies all YAML files in the directory. Kubernetes will handle dependencies, but it may take a few retries for some pods to start.

## Verification

Check that all pods are running:

```bash
kubectl get pods
```

Expected output should show:
- 2 `api-*` pods
- 2 `nginx-*` pods
- 4 `worker-indexing-*` pods
- 1 `worker-question-*` pod
- 1 `worker-default-*` pod
- 2 `graphbuilder-api-*` pods
- 4 `graphbuilder-worker-*` pods

Check services:

```bash
kubectl get services
```

View logs for debugging:

```bash
# API logs
kubectl logs -l app=api -f

# Worker logs
kubectl logs -l app=worker-indexing -f

# Nginx logs
kubectl logs -l app=nginx -f
```

## Scaling

Scale deployments as needed:

```bash
# Scale API
kubectl scale deployment api --replicas=4

# Scale indexing workers
kubectl scale deployment worker-indexing --replicas=8

# Scale GraphBuilder workers
kubectl scale deployment graphbuilder-worker --replicas=6
```

## Updates

To update a deployment with a new image:

```bash
# Update API
kubectl set image deployment/api api=your-registry/horizontal-api:v2

# Update workers
kubectl set image deployment/worker-indexing worker=your-registry/horizontal-api:v2
kubectl set image deployment/worker-question worker=your-registry/horizontal-api:v2
kubectl set image deployment/worker-default worker=your-registry/horizontal-api:v2

# Or rollout restart to pick up new image with same tag
kubectl rollout restart deployment/api
```

## Resource Limits

Current resource configuration:

| Service | CPU Request | CPU Limit | Memory Request | Memory Limit |
|---------|-------------|-----------|----------------|--------------|
| API | 250m | 1000m | 512Mi | 1Gi |
| Nginx | 100m | 500m | 128Mi | 256Mi |
| Workers | 250m | 1000m | 512Mi | 2Gi |
| GraphBuilder API | 250m | 1000m | 512Mi | 1Gi |
| GraphBuilder Workers | 250m | 1000m | 512Mi | 2Gi |

Adjust these in the respective deployment files based on your workload.

## Laravel Queue Worker Configuration

The Laravel queue workers use environment variables for production configuration:

### Worker Configuration
Set in `workers-deployment.yaml`:
- **QUEUE_CONNECTION**: Queue driver to use (default: redis)
- **QUEUE_NAMES**: Comma-separated list of queue names to process (e.g., "indexing,question,default")
- **QUEUE_TIMEOUT**: Maximum time (seconds) a job can run before timing out (default: 1800)
- **QUEUE_TRIES**: Number of times to attempt a job before failing (default: 3)
- **QUEUE_SLEEP**: Seconds to sleep when no jobs are available (default: 3)

Each worker deployment (worker-indexing, worker-question, worker-default) processes different queue priorities:
- **worker-indexing**: `indexing,question,default` - Prioritizes indexing jobs
- **worker-question**: `question,indexing,default` - Prioritizes question jobs
- **worker-default**: `default,question,indexing` - Prioritizes default jobs

To adjust these values, edit the `env` section in `workers-deployment.yaml`.

## GraphBuilder Configuration

The GraphBuilder service uses environment variables for production configuration:

### API Configuration
Set in `graphbuilder-api-deployment.yaml`:
- **PORT**: API server port (default: 9998)
- **WEB_CONCURRENCY**: Number of gunicorn workers (default: 4)
- **LOG_LEVEL**: Logging level - debug, info, warning, error (default: info)
- **GUNICORN_TIMEOUT**: Request timeout in seconds (default: 600)

### Worker Configuration
Set in `graphbuilder-worker-deployment.yaml`:
- **QUEUES**: Comma-separated list of RQ queues (default: "high,default,low")
- **LOG_LEVEL**: Logging level (default: info)
- **REDIS_URL**: Redis connection URL (from secrets)

To adjust these values, edit the `env` section in the respective deployment files.

## Troubleshooting

### Pods not starting

```bash
kubectl describe pod <pod-name>
kubectl logs <pod-name>
```

### Database connection issues

Check secrets are correctly set:

```bash
kubectl get secret app-secrets -o yaml
```

### View environment variables in a pod

```bash
kubectl exec -it <pod-name> -- env | sort
```

## Cleanup

To remove all resources:

```bash
kubectl delete -f .
```

**Warning**: This will delete all Kubernetes resources in this directory. Ensure you have backups of any important data in your managed services (PostgreSQL, Redis, Memgraph) before proceeding.

## Next Steps

After successful deployment:
1. Set up Ingress for external access (not covered here)
2. Configure SSL/TLS certificates
3. Set up monitoring and logging
4. Configure horizontal pod autoscaling (HPA)
5. Ensure backup strategies are in place for managed services (PostgreSQL, Redis, Memgraph)
