#!/bin/bash
set -e

if [ -v PROJECT_ROOT ]
then
    MOUNT_PATH="${DRUPAL_ROOT}/${PUBLIC_FILES}"
    MOUNT_PRIVATE_PATH="${PROJECT_ROOT}/${PRIVATE_FILES}"

    # Now that the volume is mounted, change owner and make it read/write
    chown www-data:www-data ${MOUNT_PATH}
    chmod -R 777 ${MOUNT_PATH}

    chown www-data:www-data ${MOUNT_PRIVATE_PATH}
    chmod -R 777 ${MOUNT_PRIVATE_PATH}
# Do this one too while we're at it (could be done in Dockerfile)
#mkdir -p /var/www/drupal/config/sync
#chown www-data:www-data /var/www/drupal/config/sync
#chmod -R 777 /var/www/drupal/config/sync
fi

# Now that volume is usable by non-root user, start up PHP on port 9000
php-fpm
