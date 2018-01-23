# This Dockerfile provides a base level of OS packages with PHP-CLI and extensions

FROM dragosdumi/php-cli:latest

COPY deploy /app

RUN cd /app && \
    composer run-script blt-alias

COPY app-sync.sh /root/app-sync.sh

# Assuming this is using the PV/PVC.
CMD /root/app-sync.sh

