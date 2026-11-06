# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-apache

# pg_dump has to be at least as new as the server it dumps, so take the client
# from the PostgreSQL apt repo instead of whatever Debian ships.
ARG PG_CLIENT_VERSION=18

# mbstring is built in up to PHP 8.5 but not in the 8.6 images, so only build
# it when it's missing.
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends ca-certificates curl; \
	. /etc/os-release; \
	install -d /usr/share/postgresql-common/pgdg; \
	curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
		https://www.postgresql.org/media/keys/ACCC4CF8.asc; \
	echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt ${VERSION_CODENAME}-pgdg main" \
		> /etc/apt/sources.list.d/pgdg.list; \
	apt-get update; \
	apt-get install -y --no-install-recommends libpq5 libpq-dev "postgresql-client-${PG_CLIENT_VERSION}"; \
	docker-php-ext-install -j"$(nproc)" pgsql; \
	if ! php -m | grep -qx mbstring; then \
		apt-get install -y --no-install-recommends libonig5 libonig-dev; \
		docker-php-ext-install -j"$(nproc)" mbstring; \
	fi; \
	apt-get purge -y --auto-remove libpq-dev libonig-dev; \
	rm -rf /var/lib/apt/lists/*; \
	php -m | grep -qx pgsql; \
	php -m | grep -qx mbstring; \
	if ! php -m | grep -qx 'Zend OPcache'; then docker-php-ext-enable opcache; fi; \
	php -m | grep -qx 'Zend OPcache'; \
	pg_dump --version

RUN set -eux; \
	cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
	a2enmod headers; \
	sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf; \
	sed -ri 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' \
		/etc/apache2/conf-available/security.conf; \
	echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf; \
	a2enconf servername

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-phppgadmin.ini"

COPY --exclude=docker . /var/www/html/
COPY docker/config.inc.php /var/www/html/conf/config.inc.php

# Set to 1 when the app is served over HTTPS (directly or behind a proxy).
ENV PHP_SESSION_COOKIE_SECURE=0

# Release tag shown as the version, e.g. "v1.2.0-mod". The release workflow
# passes the git tag; when empty the version in libraries/lib.inc.php is shown.
ARG PPA_RELEASE=
ENV PPA_RELEASE=${PPA_RELEASE}

USER www-data
EXPOSE 8080

# A static file: no PHP bootstrap and no new session every 30 seconds.
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s \
	CMD curl -fsS -o /dev/null http://127.0.0.1:8080/healthz || exit 1
