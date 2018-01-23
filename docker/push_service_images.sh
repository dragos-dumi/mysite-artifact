#!/bin/bash
set -e

# Build each image and push
ROOT_DIR=`pwd`

DOCKER_REPO="dragosdumi"

cd $ROOT_DIR/varnish
docker push ${DOCKER_REPO}/mysite-varnish:latest

cd $ROOT_DIR/nginx
docker push ${DOCKER_REPO}/mysite-nginx:latest

cd $ROOT_DIR/php-fpm
docker push ${DOCKER_REPO}/mysite-php-fpm:latest

cd $ROOT_DIR/php-cli
docker push ${DOCKER_REPO}/mysite-php-cli:latest

# Move back to ROOT_DIR
cd $ROOT_DIR

echo "Push completed."
