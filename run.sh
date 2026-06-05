#!/bin/bash

# Load from .env file
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo "Error: .env file not found"
    exit 1
fi

sudo -u $BUILD_ACCOUNT ./build.sh 2>&1
