# Raise PHP-FPM worker limit — Moodle admin + purge caches can exhaust default 8 workers (502 Bad Gateway).
ARG BASE_IMAGE
FROM $BASE_IMAGE
RUN for f in /etc/php/*/fpm/pool.d/www.conf; do \
      if [ -f "$f" ]; then \
        sed -i 's/^pm.max_children = .*/pm.max_children = 24/' "$f"; \
        sed -i 's/^pm.start_servers = .*/pm.start_servers = 6/' "$f"; \
        sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 4/' "$f"; \
        sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 12/' "$f"; \
      fi; \
    done
