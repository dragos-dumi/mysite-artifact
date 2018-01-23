#!/usr/bin/env bash

if [ -v RSYNC_SOURCE ]
then
    echo "App Image Sidecar Container"
    echo ""
    echo "Copying application into shared volume."
    echo ""

    echo "Rsync source is: ${RSYNC_SOURCE}/"
    echo "Rsync destination is: ${RSYNC_DEST}/"

    # Rsync to destination and preserve symlinks and file perms.
    rsync -vah "${RSYNC_SOURCE}/" "${PROJECT_ROOT}" --exclude="docroot/${PUBLIC_FILES}" --exclude="docroot/${PRIVATE_FILES}"

    # Keep the container alive.
    echo ""
    echo "Finished sync job, keeping container alive..."
    echo ""
fi

php /root/noop.php
