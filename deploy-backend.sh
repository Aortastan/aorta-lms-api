#!/bin/bash
# Script deploy backend-prod dengan Docker Swarm
# Usage: ./deploy-backend.sh [replicas]

# ===============================
# CONFIGURATION
# ===============================
IMAGE_NAME="backend-image:latest"
STACK_NAME="backend"
COMPOSE_FILE="docker-compose.prod.yml"
REPLICAS=${1:-4}  # default 4 replicas jika tidak diberikan argument

# ===============================
# BUILD IMAGE
# ===============================
echo "🚀 Building Docker image: $IMAGE_NAME"
docker build -t $IMAGE_NAME .

# ===============================
# INIT SWARM (if not active)
# ===============================
SWARM_STATUS=$(docker info --format '{{.Swarm.LocalNodeState}}')
if [ "$SWARM_STATUS" != "active" ]; then
    echo "🌐 Initializing Docker Swarm..."
    docker swarm init
else
    echo "✅ Docker Swarm already active"
fi

# ===============================
# DEPLOY STACK
# ===============================
echo "📦 Deploying stack: $STACK_NAME with $REPLICAS replicas"
# Update replicas in compose file dynamically (yakin deploy.prod.yml sudah pakai deploy.replicas)
docker stack deploy -c $COMPOSE_FILE $STACK_NAME

# ===============================
# SCALE SERVICE
# ===============================
echo "⚡ Scaling backend-prod to $REPLICAS replicas"
docker service update --replicas=$REPLICAS ${STACK_NAME}_backend-prod

# ===============================
# STATUS
# ===============================
echo "🔍 Stack status:"
docker stack ps $STACK_NAME
docker service ls

echo "✅ Deployment completed!"
