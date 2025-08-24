<?php
declare(strict_types=1);

final class Banner implements JsonSerializable
{
    private string  $bannerName;  // banner_top | banner_bottom
    private ?string $imageUrl;    // URL absoluta o null
    private bool    $active;

    public function __construct(string $bannerName, ?string $imageUrl = null, bool $active = false)
    {
        $this->bannerName = $bannerName;
        $this->imageUrl   = $imageUrl;
        $this->active     = $active;
    }

    public function getBannerName(): string { return $this->bannerName; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function isActive(): bool { return $this->active; }

    public function setImageUrl(?string $url): void { $this->imageUrl = $url; }
    public function setActive(bool $active): void { $this->active = $active; }

    public function __toString(): string
    {
        return "Banner({$this->bannerName}) active=" . ($this->active ? 'true' : 'false') . " url=" . ($this->imageUrl ?? 'null');
    }

    public static function fromArray(array $row): self
    {
        return new self(
            bannerName: (string)$row['banner_name'],
            imageUrl  : isset($row['image_url']) ? (string)$row['image_url'] : null,
            active    : (bool)$row['active']
        );
    }

    public function toArray(): array
    {
        return [
            'banner_name' => $this->bannerName,
            'image_url'   => $this->imageUrl,
            'active'      => $this->active,
        ];
    }

    public function jsonSerialize(): array { return $this->toArray(); }
}
