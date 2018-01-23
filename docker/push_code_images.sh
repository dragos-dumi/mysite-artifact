#!/bin/bash
set -e

# Build each image and push
ROOT_DIR=`pwd`

DOCKER_REPO="dragosdumi"


cd $ROOT_DIR

# Build the PHP-CLI image
cd $ROOT_DIR/app-image
docker push ${DOCKER_REPO}/mysite-app-image:latest

# Move back to ROOT_DIR
cd $ROOT_DIR

echo "Push completed."
