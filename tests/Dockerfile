# テスト用の PHP 8.4（Composer 入り）。tests/run-unit.sh から使う。単体テストは DB 不要
FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
