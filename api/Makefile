worker:
	php artisan queue:work --timeout=1800 --max-jobs=100 --max-time=1800

copy-vendor:
	docker compose cp api:/var/www/vendor ./
