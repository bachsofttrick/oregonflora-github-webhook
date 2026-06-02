#!/bin/bash

# Load from .env file
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo "Error: .env file not found"
    exit 1
fi

cd $BUILD_DIR

# git diff --quiet checks unstaged changes
# git diff --cached --quiet checks staged ones
STASHED=0
if ! git diff --quiet || ! git diff --cached --quiet; then
    git stash
    STASHED=1
fi

git pull --rebase

if [ $STASHED -eq 1 ]; then
    git stash pop
fi

npm run build
