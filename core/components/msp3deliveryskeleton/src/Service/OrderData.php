<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Service;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderProduct;

final class OrderData
{
    /** @var array<string, string>|null */
    private ?array $address = null;

    /** @var list<array{id: int, name: string, count: float, price: float, weight: float, cost: float, options: array<string, mixed>}>|null */
    private ?array $products = null;

    public function __construct(private readonly msOrder $order)
    {
    }

    public static function fromOrder(msOrder $order): self
    {
        return new self($order);
    }

    public function getOrder(): msOrder
    {
        return $this->order;
    }

    /**
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     phone: string,
     *     email: string,
     *     country: string,
     *     index: string,
     *     region: string,
     *     city: string,
     *     metro: string,
     *     street: string,
     *     building: string,
     *     entrance: string,
     *     floor: string,
     *     room: string,
     *     comment: string,
     *     text_address: string
     * }
     */
    public function getAddress(): array
    {
        if ($this->address !== null) {
            return $this->address;
        }
        $address = $this->order->getOne('Address');
        $this->address = !$address instanceof msOrderAddress
            ? [
                'first_name' => '',
                'last_name' => '',
                'phone' => '',
                'email' => '',
                'country' => '',
                'index' => '',
                'region' => '',
                'city' => '',
                'metro' => '',
                'street' => '',
                'building' => '',
                'entrance' => '',
                'floor' => '',
                'room' => '',
                'comment' => '',
                'text_address' => '',
            ]
            : [
                'first_name' => $this->field($address, 'first_name'),
                'last_name' => $this->field($address, 'last_name'),
                'phone' => $this->field($address, 'phone'),
                'email' => $this->field($address, 'email'),
                'country' => $this->field($address, 'country'),
                'index' => $this->field($address, 'index'),
                'region' => $this->field($address, 'region'),
                'city' => $this->field($address, 'city'),
                'metro' => $this->field($address, 'metro'),
                'street' => $this->field($address, 'street'),
                'building' => $this->field($address, 'building'),
                'entrance' => $this->field($address, 'entrance'),
                'floor' => $this->field($address, 'floor'),
                'room' => $this->field($address, 'room'),
                'comment' => $this->field($address, 'comment'),
                'text_address' => $this->field($address, 'text_address'),
            ];

        return $this->address;
    }

    /**
     * @return list<array{id: int, name: string, count: float, price: float, weight: float, cost: float, options: array<string, mixed>}>
     */
    public function getProducts(): array
    {
        if ($this->products !== null) {
            return $this->products;
        }
        $rows = [];
        $products = $this->order->getMany('Products');
        foreach ($products as $product) {
            if (!$product instanceof msOrderProduct) {
                continue;
            }
            $options = $product->get('options');
            if (is_string($options) && $options !== '') {
                $decoded = json_decode($options, true);
                $options = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($options)) {
                $options = [];
            }
            $rows[] = [
                'id' => (int) $product->get('product_id'),
                'name' => (string) $product->get('name'),
                'count' => (float) $product->get('count'),
                'price' => (float) $product->get('price'),
                'weight' => (float) $product->get('weight'),
                'cost' => (float) $product->get('cost'),
                'options' => $options,
            ];
        }
        $this->products = $rows;

        return $rows;
    }

    public function getWeight(): float
    {
        $weight = (float) $this->order->get('weight');
        if ($weight > 0) {
            return $weight;
        }
        $sum = 0.0;
        foreach ($this->getProducts() as $product) {
            $sum += $product['count'] * $product['weight'];
        }

        return $sum;
    }

    public function getQuantity(): float
    {
        $quantity = 0.0;
        foreach ($this->getProducts() as $product) {
            $quantity += $product['count'];
        }

        return $quantity;
    }

    /**
     * MiniShop3 product schema has no length/width/height columns.
     *
     * @return array{length: float, width: float, height: float}|null
     */
    public function getDimensions(): ?array
    {
        // PROVIDER: dimensions from product options or extra fields
        foreach ($this->getProducts() as $product) {
            $options = $product['options'];
            if (
                isset($options['length'], $options['width'], $options['height'])
                && is_numeric($options['length'])
                && is_numeric($options['width'])
                && is_numeric($options['height'])
            ) {
                return [
                    'length' => (float) $options['length'],
                    'width' => (float) $options['width'],
                    'height' => (float) $options['height'],
                ];
            }
        }

        return null;
    }

    public function getCartCost(): float
    {
        return (float) $this->order->get('cart_cost');
    }

    public function getTotal(): float
    {
        return (float) $this->order->get('cost');
    }

    public function getDeliveryId(): int
    {
        return (int) $this->order->get('delivery_id');
    }

    public function getPaymentId(): int
    {
        return (int) $this->order->get('payment_id');
    }

    public function getPhone(): string
    {
        return $this->getAddress()['phone'];
    }

    public function getEmail(): string
    {
        return $this->getAddress()['email'];
    }

    public function getCountry(): string
    {
        return $this->getAddress()['country'];
    }

    public function getRegion(): string
    {
        return $this->getAddress()['region'];
    }

    public function getCity(): string
    {
        return $this->getAddress()['city'];
    }

    public function getStreet(): string
    {
        return $this->getAddress()['street'];
    }

    public function getBuilding(): string
    {
        return $this->getAddress()['building'];
    }

    public function getRoom(): string
    {
        return $this->getAddress()['room'];
    }

    public function getIndex(): string
    {
        return $this->getAddress()['index'];
    }

    public function getComment(): string
    {
        $orderComment = trim((string) $this->order->get('order_comment'));
        if ($orderComment !== '') {
            return $orderComment;
        }

        return $this->getAddress()['comment'];
    }

    private function field(msOrderAddress $address, string $key): string
    {
        $value = $address->get($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
