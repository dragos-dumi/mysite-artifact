#!/usr/bin/env bash

RED='\033[0;31m'
GREEN='\033[0;32m'
WHITE='\033[1;37m'

./build_code_images.sh
if [ $? -ne 0 ]; then
  echo -e "${RED}build_code_images failed, aborting ...${WHITE}\n";
  exit -1
fi

./build_service_images.sh
if [ $? -ne 0 ]; then
  echo -e "${RED}build_service_images failed, aborting ...${WHITE}\n";
  exit -1
fi

./push_code_images.sh
if [ $? -ne 0 ]; then
  echo -e "${RED}push_code_images failed, aborting ...${WHITE}\n";
  exit -1
fi

./push_service_images.sh
if [ $? -ne 0 ]; then
  echo -e "${RED}push_service_images failed, aborting ...${WHITE}\n";
  exit -1
fi
