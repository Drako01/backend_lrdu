<?php

declare(strict_types=1);

class Categoria
{
    private ?int $idCat = null;
    private string $nombre;

    public function __construct(string $nombre)
    {
        $this->nombre = $nombre;
    }

    public function getIdCat(): ?int
    {
        return $this->idCat;
    }
    public function setIdCat(int $id): void
    {
        $this->idCat = $id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }
    public function setNombre(string $nombre): void
    {
        $this->nombre = $nombre;
    }

    public function __toString(): string
    {
        return "Categoria: {$this->nombre}";
    }

    public static function fromArray(array $data): self
    {
        $categoria = new self(
            nombre: $data['nombre']
        );

        if (isset($data['id_cat'])) {
            $categoria->setIdCat((int)$data['id_cat']);
        }

        return $categoria;
    }

    public function toArray(): array
    {
        return [
            'id_cat' => $this->idCat,
            'nombre' => $this->nombre,
        ];
    }
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
    
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON format');
        }
        return self::fromArray($data);
    }
}
