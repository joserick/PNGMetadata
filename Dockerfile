# pngmeta-dev: PHP 8.3 CLI + extensions required by joserick/png-metadata.
# Build:  docker build -t pngmeta-dev .
# Test:   docker run --rm -v "$PWD":/app pngmeta-dev bash -lc 'composer test && composer phpstan'
FROM php:8.3-cli

# php:8.3-cli ships xml/mbstring/zlib statically; exif is the only library
# requirement that must be added, plus pcov as the coverage driver for
# `composer coverage` (lighter than xdebug). GD is absent by design (v2).
RUN apt-get update \
	&& apt-get install -y --no-install-recommends git unzip \
	&& rm -rf /var/lib/apt/lists/* \
	&& docker-php-ext-install exif \
	&& pecl install pcov \
	&& docker-php-ext-enable pcov

# Composer from the official installer image (checksum-verified binary).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# The workspace is bind-mounted with the host UID; without this, git refuses
# to operate on /app and composer loses root-package version detection.
RUN git config --global --add safe.directory /app

WORKDIR /app
