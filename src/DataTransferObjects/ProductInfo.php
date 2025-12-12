<?php

namespace Mattiasgeniar\ProductInfoFetcher\DataTransferObjects;

class ProductInfo
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $url = null,
        public ?int $priceInCents = null,
        public ?string $priceCurrency = null,
        public ?string $imageUrl = null,
    ) {}

    public function isComplete(): bool
    {
        return $this->name !== null
            && $this->description !== null
            && $this->priceInCents !== null;
    }

    public function getFormattedPrice(): ?string
    {
        if ($this->priceInCents === null) {
            return null;
        }

        $price = number_format($this->priceInCents / 100, 2, '.', '');

        if ($this->priceCurrency !== null) {
            return "{$this->priceCurrency} {$price}";
        }

        return $price;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'url' => $this->url,
            'priceInCents' => $this->priceInCents,
            'priceCurrency' => $this->priceCurrency,
            'imageUrl' => $this->imageUrl,
        ];
    }
}
