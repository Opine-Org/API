docker run \
    -e "OPINE_ENV=docker" \
    --rm \
    -v "$(pwd)/../":/app \
    opine:phpunit-api \
    --bootstrap /app/tests/bootstrap.php
