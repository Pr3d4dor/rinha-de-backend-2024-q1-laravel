docker login
docker buildx build --platform linux/amd64 -t pr3d4dor/rinha-de-backend-2024-q1-php-laravel:latest .
docker push pr3d4dor/rinha-de-backend-2024-q1-php-laravel:latest
