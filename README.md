# Laravel Library Manager API

Sistema de gestión bibliotecaria implementado con arquitectura hexagonal.

## Inicio Rápido

1. **Instalar dependencias:**
```bash
composer install
```

2. **Configurar base de datos:**
```bash
cp .env.example .env
# Editar .env con credenciales de PostgreSQL
```

3. **Ejecutar migraciones:**
```bash
php artisan migrate
```

4. **Iniciar servidor:**
```bash
php artisan serve
```

## Configuración Admin Inicial

El sistema crea un usuario admin inicial desde variables de entorno:

```bash
# .env
ADMIN_USERNAME=admin
ADMIN_PASSWORD=admin123
```

## Verificación del Sistema

Ejecutar comandos de verificación de cada tarea:

```bash
php artisan verify:database-schema      # T-02
php artisan verify:domain-value-objects # T-03  
php artisan verify:book-domain          # T-04
php artisan verify:loan-domain          # T-05
php artisan verify:member-user-domain   # T-06
php artisan verify:auth-services        # T-07
php artisan verify:book-services        # T-08
php artisan verify:lend-books          # T-09
php artisan verify:return-books        # T-10
php artisan verify:cover-storage       # T-15
php artisan verify:http-routes         # T-16
```

## API Endpoints

- `GET /api/health` - Health check
- `POST /api/auth/login` - Login
- `POST /api/users/librarians` - Create librarian (admin)
- `GET /api/books` - Search books
- `POST /api/books` - Create book (admin)  
- `POST /api/loans` - Create loan
- `GET /api/loans/overdue` - Overdue books

## Docker

```bash
docker build -t library-api .
docker run -p 8000:8000 library-api
```

Sistema listo con base vacía y admin inicial configurado.