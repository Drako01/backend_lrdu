# API Backend – Los Reyes del Usado

Backend en PHP 8 + MySQL con ruteo propio, JWT auth y RBAC. Incluye endpoints públicos para catálogo y privados para administración de usuarios/productos/categorías.

> TL;DR: clonar, crear `.env`, importar SQL, levantar y pegarle a `/auth/all-products`.

---

## Stack

* **PHP 8.1+** (PDO, mbstring)
* **MySQL 8** (InnoDB, utf8mb4)
* **Apache/Nginx** (rewrite a `index.php`)
* **JWT** (Bearer token) + **RBAC** (roles enum)
* Ruteo por código (`MainRouter`, `UserRouter`, subrouters de **products** y **categories**)

---

## Estructura (alto nivel)

```
/api
  index.php                 # Front controller
  /routers
    main.router.php         # basePath /auth (auth + products + categories)
    user.router.php         # basePath /api (users CRUD)
    product.router.php
    category.router.php
  /controllers
    AuthController.php
    UserController.php
    ProductoController.php
    CategoriaController.php
  /services
  /repositories
  /middlewares
    auth.middleware.php     # JWT + authorize(roles)
  /enums
    roles.enum.php
  /helpers
    ResponseHelper.php
    verify.helper.php
  /security
    jwt.security.php
```

---

## 1) Clonado e instalación

```bash
git clone https://github.com/<org>/<repo>.git
cd <repo>
cp .env.example .env
composer install    # si usás Composer para libs (opcional)
```

### `.env` (ejemplo)

```
APP_ENV=local
APP_DEBUG=true

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=lrdu
DB_USER=lrdu_user
DB_PASS=secret

JWT_SECRET=changeme-super-secret
JWT_ISS=losreyesdelusado
JWT_AUD=frontend
JWT_TTL_MIN=120
```

> **Nunca** commitees `JWT_SECRET`. En hosting compartido, usar la DB **prefijada** (ej.: `c2731607_lrdu`) en `DB_NAME`.

---

## 2) Base de datos

### Crear esquema y tablas

Usá tu schema existente. Si querés partir de cero, este es el **mínimo viable** (resumen):

```sql
CREATE DATABASE IF NOT EXISTS lrdu CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE lrdu;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  role VARCHAR(32) NOT NULL,
  token VARCHAR(255) DEFAULT NULL,
  first_name VARCHAR(50) DEFAULT NULL,
  last_name VARCHAR(50) DEFAULT NULL,
  connected_at DATETIME DEFAULT NULL,
  disconnected_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_users_role (role),
  INDEX idx_users_connected_at (connected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS categorias (
  id_cat INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS productos (
  id_producto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT DEFAULT NULL,
  id_categoria INT UNSIGNED NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0 CHECK (stock >= 0),
  precio DECIMAL(12,2) NOT NULL DEFAULT 0.00 CHECK (precio >= 0),
  marca VARCHAR(100) DEFAULT NULL,
  modelo VARCHAR(100) DEFAULT NULL,
  caracteristicas TEXT DEFAULT NULL,
  codigo_interno VARCHAR(64) DEFAULT NULL,
  imagen_principal TEXT DEFAULT NULL,
  favorito BOOLEAN NOT NULL DEFAULT FALSE,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_productos_codigo_interno UNIQUE (codigo_interno),
  CONSTRAINT fk_productos_categoria FOREIGN KEY (id_categoria) REFERENCES categorias (id_cat)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_productos_nombre (nombre),
  INDEX idx_productos_categoria (id_categoria),
  INDEX idx_productos_activo_fav (activo, favorito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

### Índices recomendados (idempotente)

```sql
DELIMITER //

CREATE PROCEDURE ensure_lrdu_indexes()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='productos' AND INDEX_NAME='idx_productos_precio')
  THEN CREATE INDEX idx_productos_precio ON productos (precio); END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='productos' AND INDEX_NAME='idx_productos_stock')
  THEN CREATE INDEX idx_productos_stock ON productos (stock); END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='productos' AND INDEX_NAME='idx_productos_marca')
  THEN CREATE INDEX idx_productos_marca ON productos (marca); END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='productos' AND INDEX_NAME='idx_productos_modelo')
  THEN CREATE INDEX idx_productos_modelo ON productos (modelo); END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='productos' AND INDEX_NAME='idx_productos_fecha_id')
  THEN CREATE INDEX idx_productos_fecha_id ON productos (fecha_creacion, id_producto); END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='productos' AND INDEX_NAME='idx_prod_cat_fecha_id')
  THEN CREATE INDEX idx_prod_cat_fecha_id ON productos (id_categoria, fecha_creacion, id_producto); END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='productos' AND INDEX_NAME='idx_productos_fulltext')
  THEN ALTER TABLE productos ADD FULLTEXT idx_productos_fulltext (nombre, descripcion); END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='idx_users_created_at')
  THEN CREATE INDEX idx_users_created_at ON users (created_at); END IF;
END//

DELIMITER ;
CALL ensure_lrdu_indexes();
DROP PROCEDURE ensure_lrdu_indexes;
```

---

## 3) Levantar en local

### Apache (recomendado)

`.htaccess` en la raíz del `public`/`api`:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^ index.php [QSA,L]
</IfModule>
```

### PHP built-in (dev rápido)

```bash
php -S 127.0.0.1:8080 -t api
# abre http://127.0.0.1:8080/auth/all-products
```

---

## 4) Autenticación & Roles

* **JWT Bearer** en `Authorization: Bearer <token>`
* Roles (`Role::enum`): `SUPERADMIN`, `ADMIN`, `DEV`, `SELLER`, `SUPPORT`, `CLIENT`
* Middlewares:

  * `isAuthenticated()` → valida JWT
  * `authorize($allowedRoles)` → RBAC

---

## 5) Ruteo & Endpoints

### BasePaths

* **`/auth`** → Auth + Productos + Categorías (via `MainRouter`)
* **`/api`** → Usuarios (via `UserRouter`)

### Endpoints públicos (sin JWT)

* `POST /auth/register`
* `POST /auth/login`
* `GET  /auth/activate?token=...`
* `GET  /auth/reset-password-email?email=...`
* `GET  /auth/all-products`
* `GET  /auth/product-by-id/{id}`
* `GET  /auth/all-categories`
* `GET  /auth/category-by-id/{id}`

> Todo lo demás requiere JWT y rol habilitado.

### Productos (GET público / CRUD con JWT)

* `GET  /auth/all-products`
  **Query**: `per_page`, `page`, `category`, `search`, `min_price`, `max_price`, `in_stock`, `brand`, `model`, `sort_by`, `sort_dir`
  **Defaults**: `per_page=20`, `page=1`, `sort_by=fecha_creacion`, `sort_dir=DESC`
  **“Traer todo”**: `per_page=all` (o `0`)

  **Ejemplos**

  * `/auth/all-products?per_page=12&page=2&category=5`
  * `/auth/all-products?search=iphone&min_price=1000&max_price=3000&in_stock=true&sort_by=precio&sort_dir=ASC`

* `GET  /auth/product-by-id/{id}`

* `POST /auth/products` *(privado)*

* `PUT  /auth/products/{id}` *(privado)*

* `DELETE /auth/products/{id}` *(privado)*

### Categorías (análogo a productos)

* `GET  /auth/all-categories`
* `GET  /auth/category-by-id/{id}`
* `POST /auth/categories` *(privado)*
* `PUT  /auth/categories/{id}` *(privado)*
* `DELETE /auth/categories/{id}` *(privado)*

### Usuarios (basePath `/api`) – todos **privados**

* `GET    /api/users` *(SUPERADMIN/ADMIN/DEV)*
* `GET    /api/users/{id}` *(SUPERADMIN/ADMIN/DEV)*
* `POST   /api/users` *(SUPERADMIN/ADMIN)*
* `PUT    /api/users/{id}` *(todos los roles autenticados, según negocio)*
* `DELETE /api/users/{id}` *(solo SUPERADMIN)*

  * El controlador **verifica existencia** antes de eliminar; si no existe → **404**.

---

## 6) Respuestas

**OK (listado productos):**

```json
{
  "status": "success",
  "productos": {
    "items": [ /* array de productos */ ],
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 257,
      "total_pages": 13
    },
    "filters_applied": {
      "sort_by": "fecha_creacion",
      "sort_dir": "DESC"
    }
  }
}
```

**Error estándar:**

```json
{
  "status": "Error",
  "message": { "error": "Detalle del error" },
  "code": 400
}
```

---

## 7) Ejemplos cURL

```bash
# Login
curl -s -X POST https://backend.tu-dominio.com/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@site.com","password":"secret"}'

# Listado público (sin token)
curl -s https://backend.tu-dominio.com/auth/all-products?per_page=12&page=2

# Eliminar usuario (requiere SUPERADMIN)
curl -s -X DELETE https://backend.tu-dominio.com/api/users/6 \
  -H "Authorization: Bearer <TOKEN>"
```

> En **browser** no seteés `Host`; el navegador lo maneja. Usá:
> `Authorization: Bearer <TOKEN>`, `Accept: application/json`, y `Content-Type: application/json` solo cuando hay body.

---

## 8) Notas de implementación

* **Update user**: actualiza **solo** campos enviados; `email` se actualiza **solo si cambia** (case-insensitive) y pasa check de unicidad (409 si está en uso).
* **DELETE user**: busca el usuario antes; si no existe → **404**; si hay carrera y no elimina → **409**.
* **Paginación**: `per_page=all` (o `0`) desactiva LIMIT/OFFSET.
* **Búsqueda**: `LIKE` sobre `nombre/descripcion`. Si activás FULLTEXT, podés pasar a `MATCH...AGAINST` en el repo.

---

## 9) Troubleshooting

* **405 Method Not Allowed** (HTML del servidor): el vHost/WAF bloquea `DELETE/PUT`.

  * Usar dominio correcto (no IP).
  * Revisar rewrite (`.htaccess`).
  * Fallback: **method override** (`?_method=DELETE` + header `X-HTTP-Method-Override: DELETE`).
* **1062 Duplicate entry**: email UNIQUE. El service ya valida y devuelve **409** si el mail está tomado.
* **Cannot use object of type User as array**: acceder a entidad como objeto (o usar helper que soporte ambos).

---

## 10) Contribuciones

1. Crea una rama: `feat/mi-cambio`
2. Corre linters/tests si aplican
3. PR con descripción clara (antes/después, impacto en API)

---

## Licencia

 Apache License (LICENCE)[LICENCE].

---

## Roadmap breve

* `FULLTEXT` en búsqueda y switch automático si está disponible
* Tests (PHPUnit) para `users.update` (mismo email / email tomado / casing)
* Rate limiting básico para auth
* Documentar OpenAPI/Swagger

---

> Desarrollado por Alejandro Di Stefano
