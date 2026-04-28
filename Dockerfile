FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

# Ensure root requests (/) always resolve to an index file in Render.
RUN printf "DirectoryIndex index.php index.html\n" > /etc/apache2/conf-available/recoleccta-index.conf \
	&& a2enconf recoleccta-index \
	&& printf "%s\n" "<?php" "header('Content-Type: application/json');" "echo json_encode(['success' => true, 'message' => 'Recoleccta API online', 'apiBase' => '/api']);" > /var/www/html/index.php

COPY api /var/www/html/api
COPY index.php /var/www/html/index.php

EXPOSE 80
