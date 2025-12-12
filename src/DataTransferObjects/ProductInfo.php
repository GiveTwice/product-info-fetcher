<?php

namespace Mattiasgeniar\ProductInfoFetcher\DataTransferObjects;

class ProductInfo
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $url = null,
        public ?string $price = null,
        public ?string $imageUrl = null,
    ) {}

    public function isComplete(): bool
    {
        return $this->name !== null
            && $this->description !== null
            && $this->price !== null;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'url' => $this->url,
            'price' => $this->price,
            'imageUrl' => $this->imageUrl,
        ];
    }
}
