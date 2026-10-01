<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\Index(name: 'idx_products_name', columns: ['name'])]
#[ORM\Index(name: 'idx_products_price', columns: ['price'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'external_code', type: Types::STRING, length: 255, unique: true)]
    private string $externalCode;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Sale price, stored as DECIMAL string to avoid float rounding. */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $price = '0.00';

    /** Discount in % = (price − purchase price) / price × 100. */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $discount = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ProductAttribute> */
    #[ORM\OneToMany(targetEntity: ProductAttribute::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $attributes;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    public function __construct(string $externalCode)
    {
        $this->externalCode = $externalCode;
        $this->attributes = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalCode(): string
    {
        return $this->externalCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getDiscount(): ?string
    {
        return $this->discount;
    }

    public function setDiscount(?string $discount): self
    {
        $this->discount = $discount;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, ProductAttribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(string $key, string $value): ProductAttribute
    {
        $attribute = new ProductAttribute($this, $key, $value);
        $this->attributes->add($attribute);

        return $attribute;
    }

    /**
     * Replaces all attributes (orphanRemoval deletes the old rows on flush).
     *
     * @param array<string, string> $attributes
     */
    public function replaceAttributes(array $attributes): void
    {
        $this->attributes->clear();
        foreach ($attributes as $key => $value) {
            $this->addAttribute($key, $value);
        }
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(string $url, ?string $path): ProductImage
    {
        $image = new ProductImage($this, $url, $path, $this->images->count());
        $this->images->add($image);

        return $image;
    }

    /**
     * @param list<array{url: string, path: ?string}> $images
     */
    public function replaceImages(array $images): void
    {
        $this->images->clear();
        foreach ($images as $image) {
            $this->addImage($image['url'], $image['path']);
        }
    }
}
