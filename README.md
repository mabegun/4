# Проектное Бюро - Система управления проектами

## Установка

### 1. Требования
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx

### 2. Настройка базы данных

1. Создайте базу данных:
```sql
CREATE DATABASE prokb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Импортируйте схему:
```bash
mysql -u root -p prokb < database.sql
```

### 3. Настройка подключения

Отредактируйте `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'prokb');
define('DB_USER', 'ваш_пользователь');
define('DB_PASS', 'ваш_пароль');
define('APP_URL', 'https://ваш-домен.ru');
```

### 4. Права доступа

```bash
chmod 755 -R /path/to/prokb-server
chmod 777 -R /path/to/prokb-server/uploads
```

### 5. Веб-сервер

#### Apache
Файл `.htaccess` уже настроен. Убедитесь, что включен `mod_rewrite`.

#### Nginx
```nginx
server {
    listen 80;
    server_name your-domain.ru;
    root /path/to/prokb-server;
    index index.html index.php;
    
    location / {
        try_files $uri $uri/ /index.html;
    }
    
    location /api {
        try_files $uri $uri/ /api/index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## Демо-доступ

По умолчанию созданы пользователи (пароль: `123456`):

| Email | Роль |
|-------|------|
| ivanov@prokb.ru | Директор |
| sidorova@prokb.ru | ГИП |
| petrov@prokb.ru | Сотрудник |

## Структура проекта

```
prokb-server/
├── api/
│   └── index.php      # API роутер
├── assets/
│   ├── style.css      # Стили
│   └── app.js         # JavaScript
├── uploads/           # Загруженные файлы
├── config.php         # Конфигурация
├── db.php             # Функции БД
├── database.sql       # Схема БД
├── index.html         # Фронтенд
└── .htaccess          # Настройки Apache
```

## API Endpoints

### Аутентификация
- `POST /api?action=auth/login` - Вход
- `POST /api?action=auth/logout` - Выход
- `GET /api?action=auth/me` - Текущий пользователь

### Проекты
- `GET /api?action=projects` - Список проектов
- `GET /api?action=projects/{id}` - Получить проект
- `POST /api?action=projects` - Создать проект
- `PUT /api?action=projects/{id}` - Обновить проект
- `DELETE /api?action=projects/{id}` - Удалить проект

### Разделы
- `GET /api?action=sections/{id}` - Получить раздел
- `PUT /api?action=sections/{id}/status` - Обновить статус
- `POST /api?action=sections/{id}/files` - Загрузить файл

### Изыскания
- `GET /api?action=investigations/standard` - Стандартные изыскания
- `POST /api?action=projects/{id}/investigations` - Добавить изыскание

## Лицензия

MIT
