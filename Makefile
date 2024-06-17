dev:
	symfony server\:stop && \
	APP_ENV=dev symfony server\:start -d --no-tls --port 8001 && \
	yarn encore dev --watch

prod:
	symfony server\:stop && \
	APP_ENV=prod symfony server\:start -d --no-tls --port 8001 && \
	yarn encore prod --watch

stop:
	symfony server:stop

dev_fixture:
	bin/console d:s:d -f -q && \
	bin/console d:s:u -f --complete && \
	bin/console d:f:l