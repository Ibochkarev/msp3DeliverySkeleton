<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Tests\Unit;

use Ibochkarev\Msp3DeliverySkeleton\Service\OrderData;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderProduct;
use PHPUnit\Framework\TestCase;

final class OrderDataTest extends TestCase
{
    public function testReadsOrderAddressProductsAndTotals(): void
    {
        $order = $this->order();
        $data = OrderData::fromOrder($order);

        self::assertSame($order, $data->getOrder());
        self::assertSame('Moscow', $data->getCity());
        self::assertSame('101000', $data->getIndex());
        self::assertSame('+79990001122', $data->getPhone());
        self::assertSame('user@example.test', $data->getEmail());
        self::assertSame('RU', $data->getCountry());
        self::assertSame('Moscow', $data->getRegion());
        self::assertSame('Tverskaya', $data->getStreet());
        self::assertSame('1', $data->getBuilding());
        self::assertSame('12', $data->getRoom());
        self::assertSame('leave at door', $data->getComment());
        self::assertCount(2, $data->getProducts());
        self::assertSame(3.0, $data->getQuantity());
        self::assertSame(1.5, $data->getWeight());
        self::assertSame(1500.0, $data->getCartCost());
        self::assertSame(1700.0, $data->getTotal());
        self::assertSame(4, $data->getDeliveryId());
        self::assertSame(2, $data->getPaymentId());
        self::assertSame(['length' => 10.0, 'width' => 20.0, 'height' => 30.0], $data->getDimensions());
    }

    public function testWeightFallsBackToProductSum(): void
    {
        $order = $this->order();
        $order->set('weight', 0);
        $data = OrderData::fromOrder($order);
        self::assertSame(3.5, $data->getWeight());
    }

    public function testEmptyAddressAndNoDimensions(): void
    {
        $order = new msOrder(['weight' => 0, 'cart_cost' => 0, 'cost' => 0, 'order_comment' => '']);
        $data = OrderData::fromOrder($order);
        self::assertSame('', $data->getCity());
        self::assertSame('', $data->getComment());
        self::assertSame([], $data->getProducts());
        self::assertNull($data->getDimensions());
    }

    private function order(): msOrder
    {
        $address = new msOrderAddress([
            'first_name' => 'Ivan',
            'last_name' => 'Petrov',
            'phone' => '+79990001122',
            'email' => 'user@example.test',
            'country' => 'RU',
            'index' => '101000',
            'region' => 'Moscow',
            'city' => 'Moscow',
            'metro' => '',
            'street' => 'Tverskaya',
            'building' => '1',
            'entrance' => '',
            'floor' => '',
            'room' => '12',
            'comment' => 'leave at door',
            'text_address' => '',
        ]);
        $products = [
            new msOrderProduct([
                'product_id' => 10,
                'name' => 'Box',
                'count' => 2,
                'price' => 500,
                'weight' => 1,
                'cost' => 1000,
                'options' => ['length' => 10, 'width' => 20, 'height' => 30],
            ]),
            new msOrderProduct([
                'product_id' => 11,
                'name' => 'Bag',
                'count' => 1,
                'price' => 500,
                'weight' => 1.5,
                'cost' => 500,
                'options' => [],
            ]),
        ];

        return new msOrder(
            [
                'id' => 15,
                'weight' => 1.5,
                'cart_cost' => 1500,
                'cost' => 1700,
                'delivery_id' => 4,
                'payment_id' => 2,
                'order_comment' => '',
            ],
            ['Address' => $address, 'Products' => $products]
        );
    }
}
