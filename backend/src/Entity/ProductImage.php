<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_images')]
#[ORM\Index(name: 'idx_product_images_product', columns: ['product_id'])]
class ProductImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    /** Original link from the import file. */
    #[ORM\Column(type: Types::TEXT)]
    private string $url;

    /** Public path of the downloaded copy (null when download failed). */
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $path;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position;

    public function __construct(Product $product, string $url, ?string $path, int $position = 0)
    {
        $this->product = $product;
        $this->url = $url;
        $this->path = $path;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
