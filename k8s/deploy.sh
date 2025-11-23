#!/bin/bash

set -e

echo "🚀 Deploying Horizontal to Kubernetes..."
echo ""

# Check if kubectl is available
if ! command -v kubectl &> /dev/null; then
    echo "❌ kubectl not found. Please install kubectl first."
    exit 1
fi

# Check if secrets.yaml exists
if [ ! -f "secrets.yaml" ]; then
    echo "❌ secrets.yaml not found!"
    echo "Please create secrets.yaml from secrets.yaml.template and fill in your values."
    exit 1
fi

echo "📋 Step 1/6: Deploying ConfigMaps..."
kubectl apply -f app-configmap.yaml
kubectl apply -f nginx-configmap.yaml
echo "✅ ConfigMaps deployed"
echo ""

echo "🔐 Step 2/6: Deploying Secrets..."
kubectl apply -f secrets.yaml
echo "✅ Secrets deployed"
echo ""

echo "🔧 Step 3/6: Deploying API..."
kubectl apply -f api-deployment.yaml
echo "⏳ Waiting for API to be ready..."
kubectl wait --for=condition=ready pod -l app=api --timeout=300s
echo "✅ API is ready"
echo ""

echo "🌐 Step 4/6: Deploying Nginx..."
kubectl apply -f nginx-deployment.yaml
echo "⏳ Waiting for Nginx to be ready..."
kubectl wait --for=condition=ready pod -l app=nginx --timeout=300s
echo "✅ Nginx is ready"
echo ""

echo "⚙️  Step 5/6: Deploying Workers..."
kubectl apply -f workers-deployment.yaml
echo "✅ Workers deployed"
echo ""

echo "📊 Step 6/6: Deploying GraphBuilder services..."
kubectl apply -f graphbuilder-api-deployment.yaml
kubectl apply -f graphbuilder-worker-deployment.yaml
echo "✅ GraphBuilder services deployed"
echo ""

echo "🎉 Deployment complete!"
echo ""
echo "📊 Check status with:"
echo "  kubectl get pods"
echo "  kubectl get services"
echo ""
echo "📝 View logs with:"
echo "  kubectl logs -l app=api -f"
echo "  kubectl logs -l app=worker-indexing -f"
echo ""
