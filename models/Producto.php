<?php
declare(strict_types=1);

final class Producto implements JsonSerializable
{
    /** PK autoincremental (nullable hasta persistir) */
    private ?int $idProducto = null;
    private string $nombre;
    private ?string $descripcion;
    private int $idCategoria;
    private int $stock;
    private float $precio;
    private ?string $marca;
    private ?string $modelo;
    private ?string $caracteristicas;
    private ?string $codigoInterno;
    private ?string $imagenPrincipal;
    private bool $favorito;
    private bool $activo;
    private ?string $fechaCreacion;
    private ?string $fechaActualizacion;

    public function __construct(
        string $nombre,
        int $idCategoria,
        ?string $descripcion = null,
        int $stock = 0,
        float $precio = 0.00,
        ?string $marca = null,
        ?string $modelo = null,
        ?string $caracteristicas = null,
        ?string $codigoInterno = null,
        ?string $imagenPrincipal = null,
        bool $favorito = false,
        bool $activo = true,
        ?string $fechaCreacion = null,
        ?string $fechaActualizacion = null
    ) {
        $this->nombre = trim($nombre);
        $this->idCategoria = $idCategoria;
        $this->descripcion = self::t($descripcion);
        $this->setStock($stock);
        $this->setPrecio($precio);
        $this->marca = self::t($marca);
        $this->modelo = self::t($modelo);
        $this->caracteristicas = self::t($caracteristicas);
        $this->codigoInterno = self::t($codigoInterno);
        $this->imagenPrincipal = self::t($imagenPrincipal);
        $this->favorito = $favorito;
        $this->activo = $activo;
        $this->fechaCreacion = self::t($fechaCreacion);
        $this->fechaActualizacion = self::t($fechaActualizacion);
    }

    /* ==========================
        Getters / Setters
       ========================== */

    public function getIdProducto(): ?int { return $this->idProducto; }
    public function setIdProducto(?int $id): void { $this->idProducto = $id; }

    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $nombre): void { $this->nombre = trim($nombre); }

    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $descripcion): void { $this->descripcion = self::t($descripcion); }

    public function getIdCategoria(): int { return $this->idCategoria; }
    public function setIdCategoria(int $id): void { $this->idCategoria = $id; }

    public function getStock(): int { return $this->stock; }
    public function setStock(int $stock): void {
        if ($stock < 0) { throw new InvalidArgumentException('El stock no puede ser negativo.'); }
        $this->stock = $stock;
    }

    public function getPrecio(): float { return $this->precio; }
    public function setPrecio(float $precio): void {
        if ($precio < 0) { throw new InvalidArgumentException('El precio no puede ser negativo.'); }
        $this->precio = round($precio, 2);
    }

    public function getMarca(): ?string { return $this->marca; }
    public function setMarca(?string $marca): void { $this->marca = self::t($marca); }

    public function getModelo(): ?string { return $this->modelo; }
    public function setModelo(?string $modelo): void { $this->modelo = self::t($modelo); }

    public function getCaracteristicas(): ?string { return $this->caracteristicas; }
    public function setCaracteristicas(?string $caracteristicas): void { $this->caracteristicas = self::t($caracteristicas); }

    public function getCodigoInterno(): ?string { return $this->codigoInterno; }
    public function setCodigoInterno(?string $codigo): void { $this->codigoInterno = self::t($codigo); }

    public function getImagenPrincipal(): ?string { return $this->imagenPrincipal; }
    public function setImagenPrincipal(?string $imagen): void { $this->imagenPrincipal = self::t($imagen); }

    public function isFavorito(): bool { return $this->favorito; }
    public function setFavorito(bool $favorito): void { $this->favorito = $favorito; }

    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $activo): void { $this->activo = $activo; }

    public function getFechaCreacion(): ?string { return $this->fechaCreacion; }
    public function setFechaCreacion(?string $fecha): void { $this->fechaCreacion = self::t($fecha); }

    public function getFechaActualizacion(): ?string { return $this->fechaActualizacion; }
    public function setFechaActualizacion(?string $fecha): void { $this->fechaActualizacion = self::t($fecha); }

    /* ==========================
        Helpers de (de)serialización
       ========================== */

    /** Array listo para DB (snake_case) */
    public function toArray(): array
    {
        return [
            'id_producto'         => $this->idProducto,
            'nombre'              => $this->nombre,
            'descripcion'         => $this->descripcion,
            'id_categoria'        => $this->idCategoria,
            'stock'               => $this->stock,
            'precio'              => $this->precio,
            'marca'               => $this->marca,
            'modelo'              => $this->modelo,
            'caracteristicas'     => $this->caracteristicas,
            'codigo_interno'      => $this->codigoInterno,
            'imagen_principal'    => $this->imagenPrincipal,
            'favorito'            => $this->favorito,
            'activo'              => $this->activo,
            'fecha_creacion'      => $this->fechaCreacion,
            'fecha_actualizacion' => $this->fechaActualizacion,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(int $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this, $options);
    }

    /** Crea desde array flexible (acepta snake_case y camelCase) */
    public static function fromArray(array $data): self
    {
        $get = fn(array $a, string $snake, string $camel, $default = null)
            => $a[$snake] ?? $a[$camel] ?? $default;

        $favorito = self::boolify($get($data, 'favorito', 'favorito', false));
        $activo   = self::boolify($get($data, 'activo', 'activo', true));

        $p = new self(
            nombre:            (string)$get($data, 'nombre', 'nombre'),
            idCategoria:       (int)$get($data, 'id_categoria', 'idCategoria'),
            descripcion:       self::optStr($get($data, 'descripcion', 'descripcion')),
            stock:             (int)$get($data, 'stock', 'stock', 0),
            precio:            (float)$get($data, 'precio', 'precio', 0.00),
            marca:             self::optStr($get($data, 'marca', 'marca')),
            modelo:            self::optStr($get($data, 'modelo', 'modelo')),
            caracteristicas:   self::optStr($get($data, 'caracteristicas', 'caracteristicas')),
            codigoInterno:     self::optStr($get($data, 'codigo_interno', 'codigoInterno')),
            imagenPrincipal:   self::optStr($get($data, 'imagen_principal', 'imagenPrincipal')),
            favorito:          $favorito,
            activo:            $activo,
            fechaCreacion:     self::optStr($get($data, 'fecha_creacion', 'fechaCreacion')),
            fechaActualizacion:self::optStr($get($data, 'fecha_actualizacion', 'fechaActualizacion'))
        );

        $id = $get($data, 'id_producto', 'idProducto');
        if ($id !== null) {
            $p->setIdProducto((int)$id);
        }

        return $p;
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('JSON inválido: ' . json_last_error_msg());
        }
        return self::fromArray($data);
    }

    public function __toString(): string
    {
        $id = $this->idProducto !== null ? (string)$this->idProducto : 'nuevo';
        return "Producto: {$this->nombre} (ID: {$id})";
    }

    /* ==========================
        Utils internos
       ========================== */

    private static function t(?string $val): ?string
    {
        return $val === null ? null : trim($val);
    }

    private static function optStr(mixed $v): ?string
    {
        if ($v === null) return null;
        $s = (string)$v;
        $s = trim($s);
        return $s === '' ? null : $s;
    }

    private static function boolify(mixed $v): bool
    {
        if (is_bool($v)) return $v;
        // Acepta "1"/"0", 1/0, "true"/"false"
        return filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
