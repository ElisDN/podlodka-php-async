init:
	docker compose down
	docker compose pull
	docker compose build --pull
	docker compose up --detach

down:
	docker compose down --remove-orphans

run:
	@docker compose run --rm php php app.php

kill:
	docker compose kill php
