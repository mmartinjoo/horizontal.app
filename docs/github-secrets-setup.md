# GitHub Secrets Setup Guide

This document lists all GitHub Secrets required for the automated deployment workflow.

## Required Secrets

Go to your GitHub repository → Settings → Secrets and variables → Actions → New repository secret

### 1. DigitalOcean Secrets

| Secret Name | Description | How to Get |
|------------|-------------|------------|
| `DOCTL_TOKEN` | DigitalOcean API token | DigitalOcean Console → API → Generate New Token |
| `DO_CLUSTER_ID` | Kubernetes cluster ID or name | `doctl k8s cluster list` or cluster name from DO Console |

### 2. AWS Secrets (for External Secrets Operator)

| Secret Name | Description | How to Get |
|------------|-------------|------------|
| `AWS_ACCESS_KEY_ID` | AWS IAM user access key ID | See [aws-secrets-manager-setup.md](./aws-secrets-manager-setup.md) Step 3 |
| `AWS_SECRET_ACCESS_KEY` | AWS IAM user secret access key | See [aws-secrets-manager-setup.md](./aws-secrets-manager-setup.md) Step 3 |

## How to Add Secrets

1. Go to your GitHub repository
2. Click **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Enter the **Name** (exactly as shown above)
5. Paste the **Value**
6. Click **Add secret**

Repeat for each secret.

## Verification

After adding all secrets, your GitHub Actions workflow will be able to:
- ✅ Authenticate to DigitalOcean
- ✅ Access your Kubernetes cluster
- ✅ Create AWS credentials for External Secrets Operator
- ✅ Deploy your application

## Security Notes

- ✅ Secrets are encrypted at rest in GitHub
- ✅ Secrets are masked in workflow logs
- ✅ Only accessible to workflow runs
- ⚠️ Repository administrators can view secret names (but not values)
- ⚠️ Anyone who can push to main branch can read secrets via workflow

## Troubleshooting

**"Error: The secret AWS_ACCESS_KEY_ID was not found"**
→ You haven't added the secret yet. Follow steps above.

**"Authentication failed" in workflow**
→ Double-check the secret value is correct (no extra spaces, complete value).

**Which AWS credentials should I use?**
→ Use the IAM user credentials you created in the AWS setup guide. See `docs/aws-secrets-manager-setup.md` for details.
