deploy:
	rm -rf ./bootstrap/packages.php
	rm -rf ./bootstrap/services.php
	composer install --prefer-dist --optimize-autoloader --no-dev
	# delete not Drive-related folders
	find ./vendor/google/apiclient-services/src -maxdepth 1 -type d ! -name "Drive*" ! -name "src" -exec rm -rf {} +
	# delete not Drive-related files
	find vendor/google/apiclient-services/src -maxdepth 1 -type f ! -name "Drive*" -delete
	php artisan config:clear
	php artisan route:clear
	serverless deploy --verbose

run:
	serverless invoke -f artisan --data '"$(CMD)"'
