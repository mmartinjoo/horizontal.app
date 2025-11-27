internal services use Bearer tokens to cumminicate with each other

generate a token:
```
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```