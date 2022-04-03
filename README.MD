# Запуск проекта STUDY-ON

## Настройка файлов окружения
Необходимо зарегистрироввать в локальносм hosts-файле домен `study-on.local`, добавв в конец файла следующую строку

```shell
127.0.0.1 study-on.local 
```

Создадим файл `.env.local` где укажем настройки для подключения к БД, а также другую секртную информацию.
Пример содержимого файла:
```
APP_NAME=study_on
NGINX_PORT=81

APP_SECRET=...
TRUSTED_HOSTS='^study-on\.local$'

DATABASE_URL=pgsql://pguser:pgpassword@postgres:5432/study_on
PG_USER=pguser
PG_PASSWORD=pgpassword
```

## Команда для запуска

Т.к. переменные окружения хранятся в файле `.env.local` для работы через docker-compose необходимо
пользоваться следующми командами
```shell
#start containers
docker-compose --env-file .env.local  up  -d

#stop containers
docker-compose --env-file .env.local  down
```

Для удобства можно создать сокращенные команды c помощью make

