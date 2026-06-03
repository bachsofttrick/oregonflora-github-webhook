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
        git stash --quiet
        STASHED=1
    else
        echo "No uncommitted changes, skipping stash"
    fi

    echo "Pulling latest changes..."
    git pull --rebase

    if [ $STASHED -eq 1 ]; then
        echo "Restoring stashed changes..."
        git stash pop --quiet
    fi

    echo "Running build..."
    npm run build &> /dev/null
}

# Detect all BUILD_DIR* variables (BUILD_DIR, BUILD_DIR2, BUILD_DIR3, etc)
# compgen -v lists all variables, grep filters for BUILD_DIR*, sort ensures consistent order
# ${!var} gets the value of the variable name stored in $var (indirect variable expansion)
for var in $(compgen -v | grep '^BUILD_DIR' | sort); do
    dir="${!var}"
    if [ -n "$dir" ] && [ -d "$dir" ]; then
        echo "Building for $var..."
        build_project "$dir"
    fi
done
unset var
unset dir
