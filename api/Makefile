deploy:
	rm -rf ./bootstrap/packages.php
	rm -rf ./bootstrap/services.php
	rm ./bootstrap/cache/*.php
	php artisan config:clear
	php artisan route:clear
	docker buildx build --target api --push --platform linux/amd64 --provenance=false -f Dockerfile.prod -t 325384049248.dkr.ecr.us-east-1.amazonaws.com/horizontal/api:latest .
	docker buildx build --target console --push --platform linux/amd64 --provenance=false -f Dockerfile.prod -t 325384049248.dkr.ecr.us-east-1.amazonaws.com/horizontal/console:latest .
	serverless deploy --debug 2>&1 | tee deploy.log

run:
	serverless invoke -f console --data '"$(CMD)"'

copy-vendor:
	docker compose cp api:/var/www/vendor ./
