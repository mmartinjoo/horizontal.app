# External Secrets Operator - Quick Start Guide

This is a quick reference for setting up and using External Secrets Operator with AWS Secrets Manager.

## What Changed

**Before:** Secrets stored in GitHub Secrets → sed commands in GitHub Actions → K8s Secret

**After:** Secrets stored in AWS Secrets Manager → External Secrets Operator → K8s Secret (automatic sync)

## Quick Setup Steps

### 1. AWS Setup (One-time)

Follow the complete guide in [aws-secrets-manager-setup.md](./aws-secrets-manager-setup.md):

1. Create IAM policy
2. Create IAM user
3. Generate access keys
4. Create all secrets in AWS Secrets Manager

### 2. Install ESO (One-time)

```bash
# Using Helm (recommended)
helm repo add external-secrets https://charts.external-secrets.io
helm install external-secrets external-secrets/external-secrets \
  -n external-secrets --create-namespace

# Or using manifest
kubectl apply -f k8s/external-secrets-operator.yaml
```

### 3. Configure GitHub Secrets (One-time)

Add AWS credentials to GitHub repository secrets. See [github-secrets-setup.md](./github-secrets-setup.md) for details.

Required secrets:
- `AWS_ACCESS_KEY_ID` - Your AWS access key ID
- `AWS_SECRET_ACCESS_KEY` - Your AWS secret access key
- `DOCTL_TOKEN` - DigitalOcean API token
- `DO_CLUSTER_ID` - Your Kubernetes cluster ID

The GitHub Actions workflow will automatically create the AWS credentials secret in your cluster.

### 4. Deploy via GitHub Actions (Fully Automated)

Just push to the `main` branch! The GitHub Actions workflow automatically:

1. Creates AWS credentials secret in cluster
2. Deploys SecretStore and ExternalSecret
3. Waits for ESO to sync secrets from AWS
4. Deploys your application

**No manual kubectl commands needed!**

Or manually trigger:
```bash
# Manually apply if needed
kubectl apply -f k8s/aws-secret-store.yaml
kubectl apply -f k8s/app-external-secret.yaml
```

### 5. Verify

```bash
# Check ExternalSecret status
kubectl get externalsecret app-secrets
kubectl describe externalsecret app-secrets

# Check the generated K8s secret
kubectl get secret app-secrets
kubectl describe secret app-secrets

# Verify a value
kubectl get secret app-secrets -o jsonpath='{.data.APP_KEY}' | base64 --decode
```

## Daily Usage

### Adding a New Secret

1. **Add to AWS Secrets Manager:**
   ```bash
   aws secretsmanager create-secret \
     --name horizontal/prod/new-secret \
     --secret-string "secret-value" \
     --region us-east-1
   ```

2. **Add to ExternalSecret mapping:**
   Edit `k8s/app-external-secret.yaml`:
   ```yaml
   data:
   - secretKey: NEW_SECRET
     remoteRef:
       key: horizontal/prod/new-secret
   ```

3. **Apply the change:**
   ```bash
   kubectl apply -f k8s/app-external-secret.yaml
   ```

4. **Verify:**
   ```bash
   kubectl get secret app-secrets -o jsonpath='{.data.NEW_SECRET}' | base64 --decode
   ```

### Updating a Secret

1. **Update in AWS Secrets Manager:**
   ```bash
   aws secretsmanager update-secret \
     --secret-id horizontal/prod/db-password \
     --secret-string "new-password"
   ```

2. **Wait for sync (default: 1 hour) OR force immediate sync:**
   ```bash
   kubectl annotate externalsecret app-secrets \
     force-sync=$(date +%s) --overwrite
   ```

### Debugging

**Secret not syncing:**
```bash
# Check ESO logs
kubectl logs -n external-secrets deployment/external-secrets

# Check ExternalSecret events
kubectl describe externalsecret app-secrets

# Check SecretStore status
kubectl describe secretstore aws-secrets-manager
```

**Common issues:**
- AWS credentials incorrect → Check `aws-credentials` secret
- IAM permissions → Verify policy allows `secretsmanager:GetSecretValue`
- Secret doesn't exist → Check AWS Secrets Manager console
- Wrong region → Verify region in `aws-secret-store.yaml`

## GitHub Workflow

The updated workflow (`.github/workflows/deploy.yml`) now:

1. Builds and pushes Docker image
2. Deploys SecretStore and ExternalSecret
3. Waits for ESO to sync secrets
4. Deploys application

**No more:**
- ❌ Secrets in GitHub
- ❌ sed commands
- ❌ Base64 encoding in workflow

## Architecture

```
┌──────────────────────┐
│ AWS Secrets Manager  │
│  horizontal/prod/*   │
└──────────┬───────────┘
           │
           │ ESO syncs every hour
           ▼
┌──────────────────────────────┐
│  Kubernetes Cluster          │
│  ┌────────────────────────┐  │
│  │ ExternalSecret         │  │
│  │  app-secrets           │  │
│  └───────────┬────────────┘  │
│              │                │
│              ▼ creates        │
│  ┌────────────────────────┐  │
│  │ Secret (app-secrets)   │  │
│  └───────────┬────────────┘  │
│              │                │
│              ▼ consumed by    │
│  ┌────────────────────────┐  │
│  │ API Pod                │  │
│  └────────────────────────┘  │
└──────────────────────────────┘
```

## Files Overview

| File | Purpose |
|------|---------|
| `k8s/external-secrets-operator.yaml` | Installs ESO (or use Helm) |
| `k8s/aws-credentials-secret.yaml.template` | Template for AWS access keys |
| `k8s/aws-secret-store.yaml` | Configures connection to AWS |
| `k8s/app-external-secret.yaml` | Defines which secrets to sync |
| `docs/aws-secrets-manager-setup.md` | Complete setup guide |

## Security Notes

- ✅ Secrets stored securely in AWS
- ✅ AWS IAM controls access
- ✅ CloudTrail audits all access
- ✅ No secrets in Git
- ✅ Automatic rotation support
- ⚠️ AWS credentials secret still in K8s (consider IRSA for production)

## Cost

~$12/month for ~30 secrets in AWS Secrets Manager

## Next Steps

- [ ] Set up staging environment secrets
- [ ] Enable AWS CloudTrail for audit logs
- [ ] Configure automatic secret rotation
- [ ] Consider IRSA for production (more secure than access keys)
