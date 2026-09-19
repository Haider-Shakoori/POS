<?php

namespace App\Services\Sales;

use App\Models\ProductUnit;
use App\Support\Decimal;
use DomainException;

class SalesPricingService
{
    public function resolve(ProductUnit $productUnit): array
    {
        $productUnit->loadMissing(['product', 'unit']);

        if (! $productUnit->can_sell || ! $productUnit->product->is_active) {
            throw new DomainException('This product unit is not available for sale.');
        }

        $price = $productUnit->selling_price !== null
            ? Decimal::normalize($productUnit->selling_price, 2)
            : Decimal::multiplyRounded(
                $productUnit->product->selling_price,
                $productUnit->conversion_factor,
                2,
            );

        $minimum = $productUnit->minimum_selling_price;

        if ($minimum === null && $productUnit->product->minimum_selling_price !== null) {
            $minimum = Decimal::multiplyRounded(
                $productUnit->product->minimum_selling_price,
                $productUnit->conversion_factor,
                2,
            );
        }

        return [
            'price' => $price,
            'minimum_price' => $minimum !== null ? Decimal::normalize($minimum, 2) : null,
        ];
    }
}
