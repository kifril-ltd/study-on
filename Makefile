COMPOSE=docker-compose
DOCKER=docker
PHP=$(DOCKER) exec -it study_on_php
CONSOLE=$(PHP) bin/console
COMPOSER=$(PHP) composer

up:
	@${COMPOSE} up -d

down:
	@${COMPOSE} down

encore_dev:
	@${COMPOSE} --env-file .env.local run node yarn encore dev

encore_prod:
	@${COMPOSE} --env-file .env.local run node yarn encore production

clear:
	@${CONSOLE} cache:clear

migration:
	@${CONSOLE} make:migration

migrate:
	@${CONSOLE} doctrine:migrations:migrate

fixtload:
	@${CONSOLE} doctrine:fixtures:load

phpunit:
	@${PHP} bin/phpunit

# В файл local.mk можно добавлять дополнительные make-команды,
# которые требуются лично вам, но не нужны на проекте в целом
-include local.mk