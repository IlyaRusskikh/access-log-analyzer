# Описание

Тестовое задание, суть которого заключается в том, чтобы создать анализатор access.log.
Анализ включает в себя выявление промежутков времени, в которые сервер имеет отказоустойчивость ниже параметра **_-u_**.
Запрос считается отказом, если время выполнения (мс) больше порогового значения **_-t_** и/или http status code равен 500-му коду возврата (5xx).

# Установка

Для установки требуется:

- git
- Docker

Скачиваем репозиторий
`git clone https://github.com/IlyaRusskikh/access-log-analyzer.git`

Собираем контейнер Docker
`docker build -t log-analyzer .`

# Запуск

Запускаем через контейнер
`cat access.log | docker run -i log-analyzer -u 88 -t 47`

Для запуска тестов
`docker run --rm --entrypoint="" log-analyzer ./vendor/bin/phpunit`
