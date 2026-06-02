#!/bin/bash

# Load from .env file
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo "Error: .env file not found"
    exit 1
fi

echo "Changing to build directory: $BUILD_DIR"
cd $BUILD_DIR

# git diff --quiet checks unstaged changes
# git diff --cached --quiet checks staged ones
STASHED=0
if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Uncommitted changes detected, stashing..."
    git stash
    STASHED=1
else
    echo "No uncommitted changes, skipping stash"
fi

echo "Pulling latest changes..."
git pull --rebase

if [ $STASHED -eq 1 ]; then
    echo "Restoring stashed changes..."
    git stash pop
fi

echo "Running build..."
npm run build
