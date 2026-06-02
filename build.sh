#!/bin/bash

# This build script supports $BUILD_DIR2. If you declare in .env, it will build from
# that folder as well

# Load from .env file
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo "Error: .env file not found"
    exit 1
fi

build_project() {
    local dir=$1
    cd "$dir"

    # git diff --quiet checks unstaged changes
    # git diff --cached --quiet checks staged ones
    # The --quiet flag suppresses all output, only shows exit code: 1 for existing uncommitted changes
    local STASHED=0
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
    npm run build &> /dev/null
}

build_project "$BUILD_DIR"

# -n confirms the variable $BUILD_DIR2 is set and non-empty
# -d confirms the path exists
if [ -n "$BUILD_DIR2" ] && [ -d "$BUILD_DIR2" ]; then
    build_project "$BUILD_DIR2"
fi
