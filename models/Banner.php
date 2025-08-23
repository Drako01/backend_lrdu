<?php

declare(strict_types=1);

class Banner
{
    private ?int $idBanner = null;
    private string $urlBanner;

    public function __construct(string $urlBanner)
    {
        $this->urlBanner = $urlBanner;
    }

    public function getIdBanner(): ?int
    {
        return $this->idBanner;
    }
    public function setIdBanner(int $idBanner): void
    {
        $this->idBanner = $idBanner;
    }

    public function getUrlBanner(): string
    {
        return $this->urlBanner;
    }
    public function setUrlBanner(string $urlBanner): void
    {
        $this->urlBanner = $urlBanner;
    }

    public function __toString(): string
    {
        return "Banner: {$this->urlBanner}";
    }

    public static function fromArray(array $data): self
    {
        $banner = new self(
            urlBanner: $data['banner']
        );

        if (isset($data['id_banner'])) {
            $banner->setIdBanner((int)$data['id_banner']);
        }

        return $banner;
    }

    public function toArray(): array
    {
        return [
            'id_banner' => $this->idBanner,
            'banner' => $this->urlBanner,
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
