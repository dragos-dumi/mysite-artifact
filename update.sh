#!/bin/bash

RED='\033[0;31m'
GREEN='\033[0;32m'
WHITE='\033[1;37m'

# Get the full path to the directory containing this script.
SCRIPT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
cd "$SCRIPT_DIR/docroot"

echo -e "${GREEN}Enable maintenance mode...${WHITE}"
drush sset system.maintenance_mode 1

echo -e "${GREEN}Importing default configuration...${WHITE}"
drush csim -y
if [ $? -ne 0 ]; then
  echo -e "${RED}Failed to import the default configuration, aborting ...${WHITE}\n";
  exit -1
fi

echo -e "${GREEN}Running database pending updates...${WHITE}"
drush updatedb -y

echo -e "${GREEN}Updating entities...${WHITE}"
drush entup -y

drush cr

echo -e "${GREEN}Disable maintenance mode...${WHITE}"
drush sset system.maintenance_mode 0
