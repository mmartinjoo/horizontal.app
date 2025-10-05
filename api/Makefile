deploy:
	rm -rf ./bootstrap/packages.php
	rm -rf ./bootstrap/services.php
	composer install --prefer-dist --optimize-autoloader --no-dev
	# Lambda has a 262MB limit
	# unfortunately, the flysystem google extension requires the entire google SDK which is 150+ MB by default
	# we only use Drive so we delete non Drive-related folders
	find ./vendor/google/apiclient-services/src -maxdepth 1 -type d ! -name "Drive*" ! -name "src" -exec rm -rf {} +
	# delete not Drive-related files
	find vendor/google/apiclient-services/src -maxdepth 1 -type f ! -name "Drive*" -delete
	php artisan config:clear
	php artisan route:clear
	serverless deploy --verbose

run:
	serverless invoke -f artisan --data '"$(CMD)"'
