# Catálogo de Productos – Endpoint & Parámetros

**BasePath:** `/auth`
**Listado:** `GET /auth/all-products` (público)
**Detalle:** `GET /auth/product-by-id/{id}` (público)

Este endpoint soporta **paginado**, **filtros** y **ordenamiento**. Ideal para grillas, listados y búsqueda avanzada.

## Parámetros de Query

| Parámetro   | Tipo              | Default          | Rango/Valores                                             | Descripción                                                   |
| ----------- | ----------------- | ---------------- | --------------------------------------------------------- | ------------------------------------------------------------- |
| `per_page`  | `number` \| `all` | `20`             | `1..100` \| `all` \| `0`                                  | Tamaño de página. `all` o `0` desactiva paginado (trae todo). |
| `page`      | `number`          | `1`              | `>=1`                                                     | Página actual. Ignorado si `per_page=all/0`.                  |
| `category`  | `number`          | —                | `>=1`                                                     | Filtra por `id_categoria`.                                    |
| `search`    | `string`          | —                | —                                                         | Busca por `nombre` o `descripcion` (LIKE `%term%`).           |
| `min_price` | `number`          | —                | `>=0`                                                     | Precio mínimo.                                                |
| `max_price` | `number`          | —                | `>=0`                                                     | Precio máximo.                                                |
| `in_stock`  | `boolean`         | —                | `true/false`                                              | `true`: stock > 0, `false`: stock = 0.                        |
| `brand`     | `string`          | —                | —                                                         | Filtra por `marca` exacta.                                    |
| `model`     | `string`          | —                | —                                                         | Filtra por `modelo` exacto.                                   |
| `sort_by`   | `string`          | `fecha_creacion` | `fecha_creacion` \| `precio` \| `id_producto` \| `nombre` | Campo de orden.                                               |
| `sort_dir`  | `string`          | `DESC`           | `ASC` \| `DESC`                                           | Dirección de orden.                                           |

> **Notas de backend**
>
> * `per_page` se **recorta** a `1..100`.
> * `in_stock` acepta `true/false/1/0` (recomendado: `true`/`false`).
> * Orden por `sort_by` + desempate por `id_producto DESC` para scroll estable.

---

## Ejemplos de Uso (URLs)

### 1) Paginado básico

```bash
/auth/all-products
/auth/all-products?per_page=12&page=1
```

### 2) Traer **todo** (sin paginado)

```bash
/auth/all-products?per_page=all
/auth/all-products?per_page=0
```

### 3) Filtrar por categoría

```bash
/auth/all-products?category=5
/auth/all-products?category=5&per_page=24&page=2
```

### 4) Búsqueda por texto

```bash
/auth/all-products?search=iphone
/auth/all-products?search=notebook%20gamer
```

### 5) Rango de precio

```bash
/auth/all-products?min_price=1000&max_price=3000
```

### 6) Solo con stock / solo sin stock

```bash
/auth/all-products?in_stock=true
/auth/all-products?in_stock=false
```

### 7) Marca y modelo

```bash
/auth/all-products?brand=Samsung
/auth/all-products?brand=Samsung&model=Galaxy%20S22
```

### 8) Ordenamiento

```bash
/auth/all-products?sort_by=precio&sort_dir=ASC
/auth/all-products?sort_by=nombre&sort_dir=DESC
```

### 9) Combinado “real”

```bash
/auth/all-products?search=iphone&min_price=1000&max_price=3000&in_stock=true&brand=Apple&sort_by=precio&sort_dir=ASC&per_page=12&page=2
```

### 10) Con trailing slash (tolerado)

```bash
/auth/all-products/?per_page=12&page=3
```

---

## Ejemplos desde Frontend

### Fetch (nativo)

```js
const qs = new URLSearchParams({
  per_page: '12',
  page: '2',
  search: 'notebook gamer',
  min_price: '800000',
  max_price: '1500000',
  in_stock: 'true',
  sort_by: 'precio',
  sort_dir: 'ASC'
}).toString();

const res = await fetch(`/auth/all-products?${qs}`, {
  method: 'GET'
});
const data = await res.json();
// data.productos.items -> array de productos
// data.productos.pagination -> meta de paginado
```

### Axios

```js
import axios from 'axios';

const { data } = await axios.get('/auth/all-products', {
  params: {
    per_page: 24,
    page: 1,
    category: 5,
    sort_by: 'fecha_creacion',
    sort_dir: 'DESC'
  }
});

// data.productos.items
// data.productos.pagination
```

### React – Hook para mantener filtros

```js
import { useState, useEffect } from 'react';

export function useProducts(initial = {}) {
  const [filters, setFilters] = useState({
    per_page: 20,
    page: 1,
    search: '',
    category: undefined,
    min_price: undefined,
    max_price: undefined,
    in_stock: undefined,
    brand: undefined,
    model: undefined,
    sort_by: 'fecha_creacion',
    sort_dir: 'DESC',
    ...initial
  });
  const [data, setData] = useState({ items: [], pagination: {} });
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      const res = await fetch(`/auth/all-products?${new URLSearchParams(
        Object.fromEntries(
          Object.entries(filters).filter(([,v]) => v !== undefined && v !== '')
        )
      )}`);
      const json = await res.json();
      setData(json.productos);
      setLoading(false);
    };
    load();
  }, [filters]);

  return { data, filters, setFilters, loading };
}
```

---

## Respuesta (Modelo)

```json
{
  "status": "success",
  "productos": {
    "items": [
      {
        "id_producto": 101,
        "nombre": "Notebook Gamer X",
        "descripcion": "RTX 4060, 16GB RAM",
        "precio": 1299999.99,
        "stock": 7,
        "id_categoria": 5,
        "marca": "Lenovo",
        "modelo": "Legion 5",
        "fecha_creacion": "2025-08-10 12:30:00"
      },
      {
        "id_producto": 98,
        "nombre": "Notebook Office",
        "descripcion": "i5, 8GB, SSD",
        "precio": 599999.00,
        "stock": 12,
        "id_categoria": 5,
        "marca": "HP",
        "modelo": "ProBook",
        "fecha_creacion": "2025-08-09 15:45:00"
      }
    ],
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

---

## Endpoint de Detalle

### Obtener 1 producto por ID

```bash
GET /auth/product-by-id/123
```

**Ejemplo (fetch):**

```js
const res = await fetch('/auth/product-by-id/123');
const data = await res.json();
// data.productos -> objeto producto
```

---

## Recomendaciones de UI/UX (para PM exigente 😎)

* **Sort whitelist**: exponer combo `['Más recientes', 'Precio ↑', 'Precio ↓', 'Nombre A→Z']` mapeado a `{sort_by, sort_dir}`.
* **Stock**: usar toggle (`En stock solamente`) → `in_stock=true`.
* **Rango de precio**: slider doble que setee `min_price`/`max_price`.
* **Búsqueda**: debounce 250–400 ms; enviar `search` cuando tenga ≥ 2–3 chars.
* **Paginado**: `per_page` 12/20/48 y scroll a top al cambiar de página.
* **“Ver todo”**: solo en páginas administrativas → `per_page=all`.

---

## Índices sugeridos (MySQL 8.0)

```sql
-- Filtros
CREATE INDEX idx_productos_categoria ON productos (id_categoria);
CREATE INDEX idx_productos_precio    ON productos (precio);
CREATE INDEX idx_productos_stock     ON productos (stock);
CREATE INDEX idx_productos_marca     ON productos (marca);
CREATE INDEX idx_productos_modelo    ON productos (modelo);

-- Orden por fecha + desempate por id
CREATE INDEX idx_productos_fecha_id  ON productos (fecha_creacion, id_producto);

-- Búsqueda de texto (opcional pero recomendado)
ALTER TABLE productos
  ADD FULLTEXT idx_productos_fulltext (nombre, descripcion);
```

> Si mantenés `LIKE '%term%'`, el FULLTEXT rinde mucho mejor que BTREE para búsquedas generales.

---

## Errores comunes

* `400` – Parámetros inválidos (ej. `page <= 0`, `sort_by` fuera de whitelist).
* `404` – Producto inexistente (`/product-by-id/{id}`).
* `500` – Error interno (revisar logs).

---
