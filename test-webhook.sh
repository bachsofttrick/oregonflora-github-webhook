#!/bin/bash

# GitHub Webhook Test Script
# Sends a simulated GitHub push event to the webhook

# Load from .env file
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo "Error: .env file not found"
    exit 1
fi

# Configuration
WEBHOOK_URL="https://herbarium.science.oregonstate.edu/autodeploy/"

if [ -z "$SECRET" ]; then
    echo "Error: SECRET is required in .env"
    exit 1
fi

if [ -z "$BRANCH" ]; then
    echo "Error: BRANCH is required in .env"
    exit 1
fi

# Create the JSON payload
PAYLOAD=$(cat <<'EOF'
{
  "ref": "BRANCH_PLACEHOLDER",
  "pusher": {
    "name": "testuser",
    "email": "test@example.com"
  },
  "head_commit": {
    "id": "abc123def456abc123def456abc123def456abc1",
    "message": "Test deploy commit",
    "author": {
      "name": "Test User",
      "email": "test@example.com"
    }
  },
  "repository": {
    "full_name": "OregonFloraProject/symbosu",
    "name": "test-repo",
    "owner": {
      "name": "testuser"
    }
  }
}
EOF
)

# Replace branch placeholder
PAYLOAD="${PAYLOAD//BRANCH_PLACEHOLDER/$BRANCH}"

# Calculate signature
SIGNATURE="sha256=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | cut -d' ' -f2)"

echo "Testing webhook..."
echo "URL: $WEBHOOK_URL"
echo "Branch: $BRANCH"
echo "Signature: $SIGNATURE"
echo ""

# Send the request
RESPONSE=$(curl -X POST "$WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  -H "X-Hub-Signature-256: $SIGNATURE" \
  -H "X-Github-Event: push" \
  -d "$PAYLOAD" \
  -w "\n%{http_code}" \
  -s)

# Split response and status code
STATUS_CODE=$(echo "$RESPONSE" | tail -n 1)
BODY=$(echo "$RESPONSE" | head -n -1)

echo "Status: $STATUS_CODE"
echo "Response:"
echo "$BODY" | jq . 2>/dev/null || echo "$BODY"
