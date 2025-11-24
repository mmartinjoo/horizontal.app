# AWS Secrets Manager Setup Guide

This guide walks you through setting up AWS Secrets Manager to work with External Secrets Operator in your Kubernetes cluster.

## Overview

External Secrets Operator (ESO) syncs secrets from AWS Secrets Manager into Kubernetes, eliminating the need to store secrets in GitHub or manage them manually with sed commands.

## Prerequisites

- AWS Account with administrative access
- AWS CLI installed (optional but recommended)
- kubectl configured for your DigitalOcean Kubernetes cluster

## Step 1: Create IAM Policy

Create an IAM policy that allows ESO to read secrets from AWS Secrets Manager.

### Using AWS Console:

1. Go to IAM → Policies → Create Policy
2. Click JSON tab and paste:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "secretsmanager:GetSecretValue",
        "secretsmanager:DescribeSecret"
      ],
      "Resource": "arn:aws:secretsmanager:us-east-1:*:secret:horizontal/prod/*"
    }
  ]
}
```

3. Click Next
4. Name it: `horizontal-external-secrets-policy`
5. Create policy

### Using AWS CLI:

```bash
aws iam create-policy \
  --policy-name horizontal-external-secrets-policy \
  --policy-document file://iam-policy.json
```

## Step 2: Create IAM User

Create an IAM user for ESO to use.

### Using AWS Console:

1. Go to IAM → Users → Create User
2. Username: `horizontal-external-secrets`
3. **DO NOT** enable console access
4. Click Next
5. Attach the policy you created: `horizontal-external-secrets-policy`
6. Create user

### Using AWS CLI:

```bash
aws iam create-user --user-name horizontal-external-secrets

aws iam attach-user-policy \
  --user-name horizontal-external-secrets \
  --policy-arn arn:aws:iam::YOUR_ACCOUNT_ID:policy/horizontal-external-secrets-policy
```

## Step 3: Create Access Keys

Generate access keys for the IAM user.

### Using AWS Console:

1. Go to IAM → Users → horizontal-external-secrets
2. Click "Security credentials" tab
3. Scroll to "Access keys"
4. Click "Create access key"
5. Select "Application running outside AWS"
6. Click Next → Create access key
7. **IMPORTANT:** Copy both:
   - Access key ID
   - Secret access key

   You won't be able to see the secret access key again!

### Using AWS CLI:

```bash
aws iam create-access-key --user-name horizontal-external-secrets
```

Save the output somewhere secure.

## Step 4: Create Secrets in AWS Secrets Manager

Now create all your application secrets in AWS Secrets Manager.

### Using AWS Console:

1. Go to AWS Secrets Manager → Store a new secret
2. Select "Other type of secret"
3. Key/value pairs - enter your secret
4. Secret name: Follow the naming convention from `k8s/app-external-secret.yaml`
5. Leave other settings as default
6. Store secret

**Repeat for all secrets:**

| Secret Name in AWS | Example Value | Description |
|-------------------|---------------|-------------|
| `horizontal/prod/app-key` | `base64:bVHllieQNZ...` | Laravel APP_KEY |
| `horizontal/prod/db-host` | `db-postgresql-do-user...` | Database host |
| `horizontal/prod/db-port` | `25060` | Database port |
| `horizontal/prod/db-database` | `horizontal` | Database name |
| `horizontal/prod/db-username` | `doadmin` | Database user |
| `horizontal/prod/db-password` | `AVNS_...` | Database password |
| `horizontal/prod/redis-host` | `redis-do-user...` | Redis host |
| `horizontal/prod/redis-password` | `` | Redis password (empty if none) |
| `horizontal/prod/redis-port` | `25061` | Redis port |
| `horizontal/prod/redis-url` | `rediss://default:...` | Full Redis connection URL |
| `horizontal/prod/fireworks-api-key` | `fw_...` | Fireworks AI API key |
| `horizontal/prod/together-api-key` | `...` | Together AI API key |
| ... | ... | Continue for all secrets |

### Using AWS CLI (faster for bulk creation):

```bash
# Example for APP_KEY
aws secretsmanager create-secret \
  --name horizontal/prod/app-key \
  --secret-string "base64:bVHllieQNZgQXujTwWImsFpHD8UitVKgNmgV0IiPaUk=" \
  --region us-east-1

# For multiline secrets like private keys
aws secretsmanager create-secret \
  --name horizontal/prod/github-private-key \
  --secret-string file://github-private-key.pem \
  --region us-east-1
```

### Script to Create All Secrets from .env

You can use this script to bulk-create secrets from your existing `api/.env` file:

```bash
#!/bin/bash

# Map of env variable to AWS secret name
declare -A secret_map=(
  ["APP_KEY"]="horizontal/prod/app-key"
  ["DB_HOST"]="horizontal/prod/db-host"
  ["DB_PORT"]="horizontal/prod/db-port"
  ["DB_DATABASE"]="horizontal/prod/db-database"
  ["DB_USERNAME"]="horizontal/prod/db-username"
  ["DB_PASSWORD"]="horizontal/prod/db-password"
  # Add all your secrets here...
)

# Source the .env file
set -a
source api/.env
set +a

# Create secrets in AWS
for env_var in "${!secret_map[@]}"; do
  secret_name="${secret_map[$env_var]}"
  secret_value="${!env_var}"

  if [ -n "$secret_value" ]; then
    echo "Creating secret: $secret_name"
    aws secretsmanager create-secret \
      --name "$secret_name" \
      --secret-string "$secret_value" \
      --region us-east-1 2>/dev/null || \
    aws secretsmanager update-secret \
      --secret-id "$secret_name" \
      --secret-string "$secret_value" \
      --region us-east-1
  fi
done
```

## Step 5: Configure Kubernetes

Now set up the Kubernetes side.

### 5.1 Create AWS Credentials Secret

```bash
# Copy the template
cp k8s/aws-credentials-secret.yaml.template k8s/aws-credentials-secret.yaml

# Edit with your AWS credentials
# Replace YOUR_AWS_ACCESS_KEY_ID and YOUR_AWS_SECRET_ACCESS_KEY
nano k8s/aws-credentials-secret.yaml

# Apply to cluster
kubectl apply -f k8s/aws-credentials-secret.yaml

# Verify
kubectl get secret aws-credentials
```

### 5.2 Install External Secrets Operator

Using Helm (recommended):

```bash
helm repo add external-secrets https://charts.external-secrets.io
helm repo update

helm install external-secrets \
  external-secrets/external-secrets \
  -n external-secrets \
  --create-namespace
```

Or using the manifest:

```bash
kubectl apply -f k8s/external-secrets-operator.yaml
```

Verify installation:

```bash
kubectl get pods -n external-secrets
kubectl get crd | grep external-secrets
```

### 5.3 Deploy SecretStore

```bash
kubectl apply -f k8s/aws-secret-store.yaml

# Verify
kubectl get secretstore aws-secrets-manager
kubectl describe secretstore aws-secrets-manager
```

### 5.4 Deploy ExternalSecret

```bash
kubectl apply -f k8s/app-external-secret.yaml

# Verify
kubectl get externalsecret app-secrets
kubectl describe externalsecret app-secrets

# Check if the K8s Secret was created
kubectl get secret app-secrets
kubectl describe secret app-secrets
```

## Step 6: Update GitHub Workflow

The GitHub workflow has been updated to remove secret preparation steps. It now:
1. Builds and pushes Docker images
2. Applies K8s manifests (including ExternalSecret)
3. ESO automatically syncs secrets from AWS

## Verification

Check that everything works:

```bash
# Check ESO is running
kubectl get pods -n external-secrets

# Check SecretStore is ready
kubectl get secretstore

# Check ExternalSecret status
kubectl get externalsecret app-secrets -o yaml

# Check the generated K8s secret
kubectl get secret app-secrets

# Decode a value to verify
kubectl get secret app-secrets -o jsonpath='{.data.APP_KEY}' | base64 --decode
```

## Troubleshooting

### ExternalSecret shows "SecretSyncedError"

Check the ExternalSecret events:
```bash
kubectl describe externalsecret app-secrets
```

Common issues:
- AWS credentials are incorrect
- IAM policy doesn't allow access to the secret
- Secret doesn't exist in AWS Secrets Manager
- Wrong region in SecretStore

### Secrets not syncing

Check ESO logs:
```bash
kubectl logs -n external-secrets deployment/external-secrets
```

### IAM Permission Denied

Verify the IAM policy is attached:
```bash
aws iam list-attached-user-policies --user-name horizontal-external-secrets
```

## Updating Secrets

To update a secret:

1. Update it in AWS Secrets Manager (Console or CLI)
2. Wait for refresh interval (1 hour by default) OR
3. Force immediate sync:
   ```bash
   kubectl annotate externalsecret app-secrets force-sync=$(date +%s) --overwrite
   ```

## Security Best Practices

1. **Never commit `aws-credentials-secret.yaml`** - Add to `.gitignore`
2. **Use least privilege** - IAM policy only allows reading specific secrets
3. **Rotate access keys** - Regularly rotate the IAM user access keys
4. **Enable AWS CloudTrail** - Audit who accesses secrets
5. **Use different secrets per environment** - `horizontal/prod/*`, `horizontal/staging/*`
6. **Enable secret rotation** - AWS Secrets Manager supports automatic rotation

## Cost

- AWS Secrets Manager: $0.40 per secret per month + $0.05 per 10,000 API calls
- Estimated cost for ~30 secrets: ~$12/month
- External Secrets Operator: Free (open source)

## Next Steps

- Set up secrets for staging environment
- Enable AWS CloudTrail for audit logs
- Configure secret rotation for database passwords
- Consider IRSA (IAM Roles for Service Accounts) for production
