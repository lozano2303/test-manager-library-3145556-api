# Manager Library API

Sistema de gestión de biblioteca desarrollado con Laravel siguiendo arquitectura hexagonal.

## Estructura del Proyecto

```
src/Library/
├── domain/              # Entidades y objetos de valor (T-03 en adelante)
├── application/         # Casos de uso y puertos (T-07 en adelante)
│   ├── ports/outbound/  # Interfaces que el core define
│   └── services/        # Servicios de aplicación
├── adapters/            # Implementaciones concretas
│   ├── inbound/http/    # Controladores HTTP
│   └── outbound/        # Persistencia, seguridad, storage
└── bootstrap/composition/ # Único punto de composición
```

## Estado de Implementación

### ✅ T-01: Esqueleto y Health Check (COMPLETADA)
- Laravel 10 con estructura hexagonal
- Endpoint `GET /health` retorna `{"status":"ok"}`
- Docker configurado con PHP 8.2 y PostgreSQL
- Namespace `Library\` configurado

### ✅ T-02: Migración inicial (COMPLETADA)
- 6 tablas creadas: `category`, `book`, `member`, `user`, `loan`, `loan_item`
- Schema `library` con todas las restricciones
- 5 categorías fijas sembradas con UUIDs literales
- Sin columnas DEFAULT (domain establece valores)
- Índices para patrones de acceso configurados

## Configuración de Base de Datos

### PostgreSQL Configuration
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=manager_library
DB_USERNAME=postgres
DB_PASSWORD=
```

### Variables Específicas del Sistema
```env
JWT_SECRET=your-jwt-secret-here
ADMIN_USERNAME=admin
ADMIN_PASSWORD=admin123
CORS_ORIGIN=http://localhost:5173
COVERS_DIR=./var/covers
```

## Comandos de Verificación

### Verificar T-02 (Migración inicial)
```bash
# Ejecutar migraciones
php artisan migrate

# Verificar evidencia T-02
php artisan library:verify-schema
```

Este comando ejecuta las consultas de `data-model.md` §10 para verificar:
- ✅ Todas las columnas existen con tipos correctos
- ✅ Ninguna columna tiene DEFAULT
- ✅ Todas las restricciones (PK, FK, CHECK) están creadas
- ✅ Todos los índices están en su lugar
- ✅ Las 5 categorías están sembradas

### Ejecutar con Docker
```bash
# Construir imagen
docker build -t manager-library-api .

# Ejecutar contenedor
docker run -p 8000:8000 manager-library-api
```

## Endpoints Disponibles

### Health Check
- `GET /health` - Retorna `{"status":"ok"}`
- `GET /api/v1/health` - Mismo endpoint con versionado

## Principios Arquitecturales

Sigue los 12 artículos de `constitution.md`:

1. **Dominio puro** - Sin dependencias externas
2. **Puertos declarados** - El core define sus necesidades
3. **Composición única** - Un solo punto de ensamblaje
4. **SOLID aplicado** - Principios verificables
5. **Migraciones dueñas** - Schema vive en migraciones
6. **Invariantes en base** - CHECK constraints como barrera
7. **Estados calculados** - No columnas derivadas
8. **Valores congelados** - Historial inmutable
9. **Sin secretos** - Todo viene del entorno
10. **El motor gana** - Base de datos es autoridad
11. **Idioma consistente** - Código en inglés, mensajes en español
12. **Evidencia requerida** - Todo task con verificación

## Próximas Tareas

- T-03: Objetos de valor (Isbn, Quantity, DateRange)
- T-04: Dominio Book (Withdraw, Restock, baja lógica)
- T-05: Dominio Loan (AddItem, ReturnItem, estados calculados)
- ...hasta T-23

## Desarrollo

```bash
# Instalar dependencias
composer install

# Configurar entorno
cp .env.example .env
php artisan key:generate

# Ejecutar migraciones
php artisan migrate

# Verificar schema
php artisan library:verify-schema

# Ejecutar servidor
php artisan serve
```