<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
function shippingPolicy(): array
{
    return [
        'base_fee' => max(0, min(1000000, (int) appConfig('SHIPPING_BASE_FEE', '30000'))),
        'free_from' => max(0, min(1000000000, (int) appConfig('SHIPPING_FREE_FROM', '2000000'))),
    ];
}
function orderAmounts(int $subtotal): array
{
    $policy = shippingPolicy();
    $shipping = $subtotal <= 0 || $subtotal >= $policy['free_from'] ? 0 : $policy['base_fee'];
    return ['subtotal' => $subtotal, 'shipping_fee' => $shipping, 'total' => $subtotal + $shipping];
}