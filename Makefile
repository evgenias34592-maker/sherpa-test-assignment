.PHONY: up down logs test seed migrate ps

up:
	@test -f .env || cp .env.example .env
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f --tail=100

ps:
	docker compose ps

migrate:
	docker compose exec -T app php bin/console migrate

seed:
	docker compose exec -T app php bin/console seed

test:
	@test -f .env || cp .env.example .env
	docker compose up -d db
	@set -a; . ./.env; set +a; \
	i=0; until docker compose exec -T db pg_isready -U "$$DB_USER" -d "$$DB_NAME" >/dev/null 2>&1; do i=$$((i+1)); [ $$i -lt 30 ] || exit 1; sleep 1; done; \
	docker compose exec -T db psql -U "$$DB_USER" -d postgres -tc "SELECT 1 FROM pg_database WHERE datname = 'products_test'" | grep -q 1 \
	  || docker compose exec -T db psql -U "$$DB_USER" -d postgres -c "CREATE DATABASE products_test OWNER \"$$DB_USER\""; \
	cd backend && DATABASE_URL=$$(php -r 'printf("postgresql://%s:%s@127.0.0.1:%s/products_test", rawurlencode(getenv("DB_USER")), rawurlencode(getenv("DB_PASSWORD")), getenv("DB_PORT"));') vendor/bin/phpunit

phpstan:
	cd backend && vendor/bin/phpstan analyse --memory-limit=1G

cs:
	cd backend && vendor/bin/php-cs-fixer fix
