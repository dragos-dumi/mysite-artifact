#!/bin/bash
set -e

# Inspect all the files in config and extract into env variables

BUILD_NUMBER=$( date +%s )

# Build each image and push
ROOT_DIR=`pwd`

cd $ROOT_DIR

DOCKER_REPO="dragosdumi"

cd $ROOT_DIR/varnish
# Copy deploy files to the image dir
docker build \
  --tag ${DOCKER_REPO}/mysite-varnish:${BUILD_NUMBER} \
  --tag ${DOCKER_REPO}/mysite-varnish:latest \
  .

cd $ROOT_DIR/nginx
# Copy deploy files to the image dir
docker build \
  --tag ${DOCKER_REPO}/mysite-nginx:${BUILD_NUMBER} \
  --tag ${DOCKER_REPO}/mysite-nginx:latest \
  .

cd $ROOT_DIR/php-fpm
# Copy deploy files to the image dir
docker build \
  --tag ${DOCKER_REPO}/mysite-php-fpm:${BUILD_NUMBER} \
  --tag ${DOCKER_REPO}/mysite-php-fpm:latest \
  .

cd $ROOT_DIR/php-cli
# Copy deploy files to the image dir
docker build \
  --tag ${DOCKER_REPO}/mysite-php-cli:${BUILD_NUMBER} \
  --tag ${DOCKER_REPO}/mysite-php-cli:latest \
  .

cd $ROOT_DIR/mysql
# Copy deploy files to the image dir
docker build \
  --tag ${DOCKER_REPO}/mysite-mysql:${BUILD_NUMBER} \
  --tag ${DOCKER_REPO}/mysite-mysql:latest \
  .

# Move back to ROOT_DIR
cd $ROOT_DIR

echo "Build completed."
