# Kubernetes Deployment Guide

This directory contains Kubernetes manifests for deploying the Horizontal application to a DigitalOcean Kubernetes cluster.

## Architecture Overview

The deployment includes:
- **API Service**: Laravel PHP-FPM application (2 replicas)
- **Nginx**: Web server fronting the API (2 replicas)
  - Uses initContainer to copy Laravel's public files from API image
  - Shares volume with main nginx container via emptyDir
- **Workers**: Three types of queue workers
  - `worker-indexing`: 4 replicas for indexing and question queues
  - `worker-question`: 1 replica for question prioritization
  - `worker-default`: 1 replica for default queue
- **GraphBuilder API**: Python API service (2 replicas)
- **GraphBuilder Workers**: Python RQ workers (4 replicas)

## Node Pool Architecture

This deployment uses **two separate node pools** to isolate user-facing workloads from background processing:

### **app-pool** (User-Facing Workloads)
- **Purpose**: Serves user requests with low latency
- **Workloads**: API, nginx, GraphBuilder API, migrations
- **Recommended size**: `s-2vcpu-4gb` or `s-4vcpu-8gb`
- **Recommended replicas**: 2-3 nodes (min: 2, max: 5)
- **Auto-scaling**: Based on CPU utilization (70% target)

### **worker-pool** (Background Processing)
- **Purpose**: Handles CPU-intensive background jobs
- **Workloads**: Laravel queue workers, GraphBuilder workers
- **Recommended size**: `c-4` (CPU-optimized) or `s-4vcpu-8gb`
- **Recommended replicas**: 4-6 nodes (min: 2, max: 10)
- **Auto-scaling**: Based on CPU utilization (80% target)

### Benefits
- ✅ **Resource isolation**: Heavy worker jobs won't impact API performance
- ✅ **Independent scaling**: Scale worker nodes during high indexing load
- ✅ **Cost optimization**: Right-size nodes for different workload types
- ✅ **Better observability**: Separate metrics per workload type

### Workload Assignment

| Workload | Node Pool | Reason |
|----------|-----------|--------|
| API (PHP-FPM) | app-pool | User-facing, needs low latency |
| Nginx | app-pool | User-facing, serves requests |
| GraphBuilder API | app-pool | User-facing API endpoints |
| Migration Job | app-pool | Database access, one-time execution |
| worker-indexing | worker-pool | CPU-heavy indexing operations |
| worker-question | worker-pool | Background question processing |
| worker-default | worker-pool | Background job processing |
| GraphBuilder workers | worker-pool | CPU-heavy graph operations |

### Creating Node Pools in DigitalOcean

When creating your Kubernetes cluster:

1. **Create app-pool:**
   - Name: `app-pool`
   - Node size: `s-2vcpu-4gb` (2 vCPUs, 4GB RAM, $24/month)
   - Node count: 2-3 nodes
   - Enable auto-scaling: min 2, max 5
   - No additional taints needed

2. **Create worker-pool:**
   - Name: `worker-pool`
   - Node size: `s-4vcpu-8gb` (4 vCPUs, 8GB RAM, $48/month) or `c-4` (CPU-optimized)
   - Node count: 4-6 nodes
   - Enable auto-scaling: min 2, max 10
   - No additional taints needed

**Note**: All deployments use `nodeSelector` to prefer their designated pool, but can fall back to other pools if needed (e.g., during high load or scaling events). This provides flexibility while maintaining separation.

## Prerequisites

1. DigitalOcean Kubernetes cluster configured with **two node pools** (see above)
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

# 3. Run Database Migrations
kubectl apply -f api-migration-job.yaml

# Wait for migrations to complete
kubectl wait --for=condition=complete job/api-migrations --timeout=300s

# Check migration logs if needed
kubectl logs job/api-migrations

# 4. Deploy API
kubectl apply -f api-deployment.yaml

# Wait for API to be ready
kubectl wait --for=condition=ready pod -l app=api --timeout=300s

# 5. Deploy Nginx
kubectl apply -f nginx-deployment.yaml

# 6. Deploy Workers
kubectl apply -f workers-deployment.yaml

# 7. Deploy GraphBuilder services
kubectl apply -f graphbuilder-api-deployment.yaml
kubectl apply -f graphbuilder-worker-deployment.yaml

# 8. Deploy Frontend applications
kubectl apply -f central-app-deployment.yaml
kubectl apply -f tenant-app-deployment.yaml

# 9. Install Ingress Controller (one-time setup)
kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.11.1/deploy/static/provider/do/deploy.yaml

# Wait for Load Balancer to be provisioned
kubectl wait --namespace ingress-nginx \
  --for=condition=ready pod \
  --selector=app.kubernetes.io/component=controller \
  --timeout=300s

# 10. Deploy Ingress routing rules
kubectl apply -f ingress.yaml

# Get Load Balancer IP for DNS configuration
kubectl get svc -n ingress-nginx ingress-nginx-controller -o jsonpath='{.status.loadBalancer.ingress[0].ip}'
```

## Ingress and Load Balancer Setup

This deployment uses **Ingress** for HTTP routing with a single DigitalOcean Load Balancer ($12/month).

### Architecture Overview

```
Internet
   ↓
DigitalOcean Load Balancer (auto-created, $12/month)
   ↓
nginx Ingress Controller (pods in cluster)
   ↓
Ingress Rules (path-based routing)
   ↓
Services → Pods
```

### Multi-Tenancy Routing

The application uses a sophisticated routing setup:

**Root Domain (`horizontal.app`):**
- `/api` → Laravel API (tenant-agnostic routes)
- `/` → Central landing page

**Tenant Subdomains (`*.horizontal.app`):**
- `tenant1.horizontal.app/graphbuilder` → GraphBuilder API
- `tenant1.horizontal.app/api` → Laravel API (tenant routes)
- `tenant1.horizontal.app/` → Vue tenant app (SPA)

### Installing Ingress Controller

**Step 1:** Install nginx Ingress Controller (one-time setup):

```bash
# Install nginx ingress controller for DigitalOcean
kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.11.1/deploy/static/provider/do/deploy.yaml

# Wait for Load Balancer to be provisioned (~2 minutes)
kubectl wait --namespace ingress-nginx \
  --for=condition=ready pod \
  --selector=app.kubernetes.io/component=controller \
  --timeout=300s

# Get Load Balancer IP
kubectl get svc -n ingress-nginx ingress-nginx-controller
```

Copy the `EXTERNAL-IP` value - this is your Load Balancer's public IP.

**Step 2:** Deploy Ingress rules:

```bash
kubectl apply -f ingress.yaml
```

**Step 3:** Configure DNS (in your domain registrar):

```
A Record: horizontal.app      → <LOAD-BALANCER-IP>
A Record: *.horizontal.app    → <LOAD-BALANCER-IP>
```

The wildcard record handles all tenant subdomains automatically!

**Step 4:** Verify Ingress is working:

```bash
# Check ingress resources
kubectl get ingress

# Should show:
# NAME                  HOSTS                    ADDRESS           PORTS
# horizontal-root       horizontal.app           157.245.x.x       80
# horizontal-tenants    *.horizontal.app         157.245.x.x       80
```

### Traffic Flow Examples

**Root landing page:**
```
https://horizontal.app
  → Load Balancer
  → Ingress Controller
  → horizontal-root Ingress (path: /)
  → central-app Service
  → central-app Pod
```

**Root API:**
```
https://horizontal.app/api/users
  → Load Balancer
  → Ingress Controller
  → horizontal-root Ingress (path: /api)
  → nginx Service
  → nginx Pod → API Pod (Laravel)
```

**Tenant app:**
```
https://tenant1.horizontal.app
  → Load Balancer
  → Ingress Controller
  → horizontal-tenants Ingress (path: /)
  → tenant-app Service
  → tenant-app Pod (Vue SPA)
```

**Tenant API:**
```
https://tenant1.horizontal.app/api/projects
  → Load Balancer
  → Ingress Controller
  → horizontal-tenants Ingress (path: /api)
  → nginx Service
  → nginx Pod → API Pod (Laravel with subdomain)
```

**GraphBuilder:**
```
https://tenant1.horizontal.app/graphbuilder/api/build
  → Load Balancer
  → Ingress Controller
  → horizontal-tenants Ingress (path: /graphbuilder)
  → graphbuilder-api Service
  → graphbuilder-api Pod
```

### Testing Endpoints

After deployment, test each route:

```bash
# Get Load Balancer IP
LB_IP=$(kubectl get svc -n ingress-nginx ingress-nginx-controller -o jsonpath='{.status.loadBalancer.ingress[0].ip}')

# Test root landing page
curl -H "Host: horizontal.app" http://$LB_IP/

# Test root API
curl -H "Host: horizontal.app" http://$LB_IP/api/health

# Test tenant app
curl -H "Host: tenant1.horizontal.app" http://$LB_IP/

# Test tenant API
curl -H "Host: tenant1.horizontal.app" http://$LB_IP/api/health

# Test GraphBuilder
curl -H "Host: tenant1.horizontal.app" http://$LB_IP/graphbuilder/health
```

Once DNS is configured, test with actual domains:

```bash
curl https://horizontal.app
curl https://horizontal.app/api/health
curl https://tenant1.horizontal.app
curl https://tenant1.horizontal.app/api/health
curl https://tenant1.horizontal.app/graphbuilder/health
```

### Adding SSL/TLS (Recommended for Production)

Install cert-manager for automatic Let's Encrypt SSL certificates:

```bash
# Install cert-manager
kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.13.0/cert-manager.yaml

# Create Let's Encrypt issuer (after cert-manager is ready)
cat <<EOF | kubectl apply -f -
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: your-email@example.com
    privateKeySecretRef:
      name: letsencrypt-prod
    solvers:
    - http01:
        ingress:
          class: nginx
EOF
```

Then update ingress.yaml to add SSL:

```yaml
metadata:
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod
    nginx.ingress.kubernetes.io/ssl-redirect: "true"
spec:
  tls:
  - hosts:
    - horizontal.app
    secretName: horizontal-app-tls
  - hosts:
    - "*.horizontal.app"
    secretName: horizontal-tenants-tls
```

Certificates will be automatically provisioned and renewed!

### Path Routing Priority

Ingress matches paths in order of specificity. For tenant subdomains:

1. `/graphbuilder` → GraphBuilder API (most specific)
2. `/api` → Laravel API
3. `/` → Vue tenant app (catch-all)

This ensures API and GraphBuilder routes are matched before the Vue SPA catch-all.

### Cost Breakdown

**With Ingress:**
- 1 Load Balancer: $12/month
- Total: **$12/month**

**Without Ingress (separate LoadBalancer Services):**
- nginx: $12/month
- graphbuilder-api: $12/month
- central-app: $12/month
- tenant-app: $12/month
- Total: **$48/month**

**Savings: $36/month**

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
- 2 `central-app-*` pods
- 2 `tenant-app-*` pods

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
# 1. Run migrations for new version (if needed)
# First, delete the old migration job
kubectl delete job api-migrations

# Then run migrations with new image version
kubectl apply -f api-migration-job.yaml
kubectl wait --for=condition=complete job/api-migrations --timeout=300s

# 2. Update API
kubectl set image deployment/api api=your-registry/horizontal-api:v2

# 3. Update workers
kubectl set image deployment/worker-indexing worker=your-registry/horizontal-api:v2
kubectl set image deployment/worker-question worker=your-registry/horizontal-api:v2
kubectl set image deployment/worker-default worker=your-registry/horizontal-api:v2

# Or rollout restart to pick up new image with same tag
kubectl rollout restart deployment/api
```

## Nginx Deployment Pattern

The nginx deployment uses an **initContainer pattern** to solve the problem of serving Laravel's public files:

### The Challenge
- **PHP-FPM** (API pods) has Laravel's public directory with `index.php`, `.htaccess`, etc.
- **Nginx** (separate deployment) needs these files to route requests properly
- These are separate pods, so they don't share filesystems by default

### The Solution
The nginx deployment uses:
1. **emptyDir volume**: A temporary empty directory shared between containers in the pod
2. **initContainer**: Runs before nginx starts, copies files from the API image to the emptyDir
3. **nginx container**: Mounts the now-populated emptyDir at `/var/www/public`

### How It Works
```
Pod Start
  ↓
initContainer (copy-public-files) runs
  - Uses API image
  - Copies /var/www/public/* to emptyDir
  - Completes
  ↓
nginx container starts
  - Mounts emptyDir at /var/www/public
  - Has access to index.php, .htaccess, etc.
  - Routes requests properly
```

### Why This Approach?
- ✅ Clean separation: API and nginx scale independently
- ✅ No custom nginx images needed
- ✅ Works with existing docker images
- ✅ Files are guaranteed to match API version

## Resource Limits

Current resource configuration:

| Service | CPU Request | CPU Limit | Memory Request | Memory Limit |
|---------|-------------|-----------|----------------|--------------|
| API | 250m | 1000m | 512Mi | 1Gi |
| Nginx | 100m | 500m | 128Mi | 256Mi |
| Nginx initContainer | 50m | 200m | 64Mi | 128Mi |
| Workers | 250m | 1000m | 512Mi | 2Gi |
| GraphBuilder API | 250m | 1000m | 512Mi | 1Gi |
| GraphBuilder Workers | 250m | 1000m | 512Mi | 2Gi |
| Central App (Landing) | 100m | 500m | 128Mi | 256Mi |
| Tenant App (Vue SPA) | 100m | 500m | 128Mi | 256Mi |

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

### Health Check Endpoints

The GraphBuilder API provides the following health check endpoints:

- **GET /health**: Basic liveness check - returns 200 if the Flask app is running
  - Used by Kubernetes liveness and startup probes
  - Lightweight, no external dependency checks

- **GET /readiness**: Readiness check - verifies external dependencies (Redis)
  - Used by Kubernetes readiness probe
  - Returns 200 if ready to accept traffic, 503 if dependencies unavailable
  - Checks Redis connectivity before marking pod as ready

The GraphBuilder worker uses an exec-based liveness probe that checks if the RQ worker process is running.

## Troubleshooting

### Migration job failed

Check the migration job status and logs:

```bash
# Check job status
kubectl get job api-migrations

# View migration logs
kubectl logs job/api-migrations

# Delete failed job to retry
kubectl delete job api-migrations
kubectl apply -f api-migration-job.yaml
```

### Pods not starting

```bash
kubectl describe pod <pod-name>
kubectl logs <pod-name>
```

### Nginx initContainer issues

If nginx pods are stuck in `Init:0/1` state:

```bash
# Check initContainer logs
kubectl logs <nginx-pod> -c copy-public-files

# Verify files were copied
kubectl exec -it <nginx-pod> -- ls -la /var/www/public/

# Check initContainer status
kubectl describe pod <nginx-pod>
```

Common issues:
- **Image pull errors**: Ensure API image is accessible from cluster
- **Permission errors**: Check that files are readable
- **Empty directory**: Verify source path `/var/www/public/*` exists in API image

### Health check failures

If GraphBuilder API pods are failing health checks:

```bash
# Check if health endpoint is accessible
kubectl exec -it <graphbuilder-api-pod> -- curl http://localhost:9998/health

# Check readiness endpoint
kubectl exec -it <graphbuilder-api-pod> -- curl http://localhost:9998/readiness

# View detailed pod events
kubectl describe pod <graphbuilder-api-pod>
```

If GraphBuilder worker pods are restarting:

```bash
# Check if RQ worker process is running
kubectl exec -it <graphbuilder-worker-pod> -- pgrep -f "rq worker"

# View worker logs
kubectl logs <graphbuilder-worker-pod>
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

### Pod scheduled on wrong node pool

Check which node a pod is running on:

```bash
# See which nodes pods are running on
kubectl get pods -o wide

# Check node labels to verify pool
kubectl get nodes --show-labels

# Verify pod nodeSelector
kubectl get pod <pod-name> -o yaml | grep -A 2 nodeSelector
```

If pods are consistently being scheduled on the wrong pool:
1. **Check node pool exists**: `kubectl get nodes -l doks.digitalocean.com/node-pool=app-pool`
2. **Check node pool has capacity**: `kubectl describe nodes`
3. **Check for pending pods**: `kubectl get pods -A | grep Pending`
4. **Enable cluster autoscaler** in DigitalOcean if not already enabled

**Expected behavior with nodeSelector only:**
- Pods prefer their designated pool
- Can fall back to other pools if designated pool is full
- This is intentional for flexibility

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
