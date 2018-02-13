#!/bin/bash
set -e

# Inspect all the files in config and extract into env variables

BUILD_NUMBER=$( date +%s )

# Build each image and push
ROOT_DIR=`pwd`

cd $ROOT_DIR

DOCKER_REPO="dragosdumi"

# Build the PHP-FPM image
cd $ROOT_DIR/app-image

# Update deploy directory.
if [ -d "deploy" ]; then
  cd deploy
  git pull
else
  git clone git@github.com:dragos-dumi/mysite-artifact.git deploy
fi

cd $ROOT_DIR/app-image

# Copy deploy files to the image dir
docker build \
  --tag ${DOCKER_REPO}/mysite-app-image:${BUILD_NUMBER} \
  --tag ${DOCKER_REPO}/mysite-app-image:latest \
  .
# Clean files after build
#rm -rf deploy

# Move back to ROOT_DIR
cd $ROOT_DIR

echo "Build completed."
