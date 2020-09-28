<?php

namespace PicoMapper;

use PHPUnit\Framework\MockObject\MockObject;
use PicoDb\Database;

class MappingTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Database
     */
    private $db;

    /**
     * @var callable|MockObject
     */
    private $hook;

    public function setUp()
    {
        $this->db = new Database(['driver' => 'sqlite', 'filename' => ':memory:']);
        $this->hook = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();

        $seed = file_get_contents(__DIR__ . '/Fixtures.sql');
        $queries = explode(';', $seed);

        foreach ($queries as $query) {
            $this->db->execute($query);
        }
    }

    public function tearDown()
    {
        $this->db = null;
        $this->hook = null;
    }

    public function testFindAll()
    {
        $customers = $this->getMapping()->findAll();

        $this->assertCount(2, $customers);

        $this->assertEquals('John Doe', $customers[0]['name']);
        $this->assertCount(2, $customers[0]['orders']);
        $this->assertCount(3, $customers[0]['orders'][0]['items']);
        $this->assertCount(1, $customers[0]['orders'][1]['items']);

        $this->assertEquals('Jane Doe', $customers[1]['name']);
        $this->assertCount(1, $customers[1]['orders']);
        $this->assertCount(2, $customers[1]['orders'][0]['items']);
        $this->assertEquals('$10', $customers[1]['orders'][0]['discount']['description']);
        $this->assertEquals(10, $customers[1]['orders'][0]['discount']['amount']);
    }

    public function testFindOne()
    {
        $customer = $this->getMapping()->eq('id', 2)->findOne();

        $this->assertEquals('Jane Doe', $customer['name']);
        $this->assertCount(1, $customer['orders']);
        $this->assertCount(2, $customer['orders'][0]['items']);

        $this->assertEquals('2018-01-02', $customer['orders'][0]['date_created']);
        $this->assertEquals('Bread', $customer['orders'][0]['items'][0]['description']);
        $this->assertEquals(120, $customer['orders'][0]['items'][0]['amount']);
        $this->assertEquals('Yogurt', $customer['orders'][0]['items'][1]['description']);
        $this->assertEquals(400, $customer['orders'][0]['items'][1]['amount']);
    }

    public function testInsert()
    {
        $customer = [
            'id' => 3,
            'name' => 'Dave Matthews',
            'orders' => [
                [
                    'id' => 4,
                    'date_created' => '2018-02-01',
                    'discount' => [
                        'description' => 'Dessert Discount',
                        'amount' => 20
                    ],
                    'items' => [
                        [
                            'id' => 7,
                            'description' => 'Ice Cream',
                            'amount' => 400
                        ],
                        [
                            'id' => 8,
                            'description' => 'Cookies',
                            'amount' => '230'
                        ]
                    ]
                ]
            ]
        ];

        $this
            ->hook
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo('customers'),
                $this->equalTo(['id' => 3]),
                $this->equalTo($customer)
            );

        $this->getMapping()->insert($customer);

        $saved = $this->getMapping()->eq('id', 3)->findOne();
        $this->assertEquals('Dave Matthews', $saved['name']);
        $this->assertCount(1, $saved['orders']);
        $this->assertCount(2, $saved['orders'][0]['items']);
        $this->assertEquals('Dessert Discount', $saved['orders'][0]['discount']['description']);
        $this->assertEquals(20, $saved['orders'][0]['discount']['amount']);

        $this->assertEquals('2018-02-01', $saved['orders'][0]['date_created']);
        $this->assertEquals('Ice Cream', $saved['orders'][0]['items'][0]['description']);
        $this->assertEquals(400, $saved['orders'][0]['items'][0]['amount']);
        $this->assertEquals('Cookies', $saved['orders'][0]['items'][1]['description']);
        $this->assertEquals(230, $saved['orders'][0]['items'][1]['amount']);
    }

    public function testUpdate()
    {
        $customer = $this->getMapping()->eq('id', 1)->findOne();
        $original = $customer;

        $customer['orders'][0]['fulfillment'] = ['employee_id' => 2];
        $customer['orders'][0]['items'][1]['description'] = 'Jumbo Eggs';
        $customer['orders'][0]['items'][] = ['id' => 7, 'description' => 'Cheese', 'amount' => 300];

        unset($customer['orders'][0]['items'][0]);
        unset($customer['orders'][1]);

        $this
            ->hook
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo('customers'),
                $this->equalTo(['id' => 1]),
                $this->equalTo($customer),
                $this->equalTo($original)
            );

        $this->getMapping()->update($customer);
        $saved = $this->getMapping()->eq('id', 1)->findOne();
        $this->assertCount(3, $saved['orders'][0]['items']);
        $this->assertEquals(2, $saved['orders'][0]['items'][0]['id']);
        $this->assertEquals('Jumbo Eggs', $saved['orders'][0]['items'][0]['description']);
        $this->assertEquals('2019-01-02 03:04:05', $saved['orders'][0]['items'][0]['modified']);
        $this->assertEmpty($saved['orders'][0]['items'][1]['modified']);
        $this->assertEquals(7, $saved['orders'][0]['items'][2]['id']);
        $this->assertEquals('Cheese', $saved['orders'][0]['items'][2]['description']);
    }

    public function testRemove()
    {
        $this
            ->hook
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo('customers'),
                $this->equalTo(['id' => 1])
            );

        $this->getMapping()->eq('id', 1)->remove();

        $removed = $this->getMapping()->eq('id', 1)->findOne();
        $this->assertNull($removed);
    }

    public function  testReadOnlyInsert()
    {
        $customer = [
            'id' => 10,
            'name' => 'Dave Matthews',
            'orders' => [
                [
                    'id' => 40,
                    'date_created' => '2018-02-01',
                    'items' => [
                        [
                            'id' => 7,
                            'description' => 'Ice Cream',
                            'amount' => 400
                        ],
                        [
                            'id' => 80,
                            'description' => 'Cookies',
                            'amount' => '230'
                        ]
                    ]
                ]
            ]
        ];

        $this->getReadOnlyMapping()->insert($customer);

        $saved = $this->getReadOnlyMapping()->eq('id', 10)->findOne();

        $this->assertNotEmpty($saved);
        $this->assertEquals('Dave Matthews', $saved['name']);
        $this->assertCount(1, $saved['orders']);
        $this->assertCount(0, $saved['orders'][0]['items']);
    }

    public function testReadOnlyUpdate()
    {
        $customer = $this->getReadOnlyMapping()->eq('id', 1)->findOne();

        $customer['orders'][0]['date_created'] = '2018-01-10';
        $customer['orders'][0]['items'][1]['description'] = 'Jumbo Eggs';
        $customer['orders'][0]['items'][] = ['id' => 7, 'description' => 'Cheese', 'amount' => 300];

        unset($customer['orders'][0]['items'][0]);
        unset($customer['orders'][1]);

        $this->getReadOnlyMapping()->update($customer);

        $saved = $this->getReadOnlyMapping()->eq('id', 1)->findOne();

        $this->assertCount(1, $saved['orders']);
        $this->assertEquals(1, $saved['orders'][0]['id']);
        $this->assertEquals('2018-01-10', $saved['orders'][0]['date_created']);
        $this->assertCount(3, $saved['orders'][0]['items']);
        $this->assertEquals(1, $saved['orders'][0]['items'][0]['id']);
        $this->assertEquals('Milk', $saved['orders'][0]['items'][0]['description']);
        $this->assertEquals(2, $saved['orders'][0]['items'][1]['id']);
        $this->assertEquals('Eggs', $saved['orders'][0]['items'][1]['description']);
        $this->assertEquals(3, $saved['orders'][0]['items'][2]['id']);
        $this->assertEquals('Bacon', $saved['orders'][0]['items'][2]['description']);
    }

    public function testInsertRollback()
    {
        $customer = [
            'id' => 3,
            'name' => 'Dave Matthews',
            'orders' => [
                [
                    'id' => 4,
                    'date_created' => '2018-02-01',
                    'discount' => [
                        'description' => 'Dessert Discount',
                        'amount' => 20
                    ],
                    'items' => [
                        [
                            'id' => 7,
                            'description' => 'Ice Cream',
                            'amount' => 400
                        ],
                        [
                            'id' => 7,
                            'description' => 'Cookies',
                            'amount' => '230'
                        ]
                    ]
                ]
            ]
        ];

        $this->getMapping()->insert($customer);
        $inserted = $this->getMapping()->eq('id', 3)->findOne();

        $this->assertNull($inserted);
    }

    public function testUpdateRollback()
    {
        $customer = $this->getMapping()->eq('id', 1)->findOne();
        $original = $customer;

        $customer['orders'][0]['items'][] = ['id' => '3'];
        $this->getMapping()->update($customer);

        $updated = $this->getMapping()->eq('id', 1)->findOne();
        $this->assertEquals($original, $updated);
    }

    /**
     * Returns a new mapping for testing.
     *
     * @return Mapping
     */
    public function getMapping()
    {
        $discount = (new Definition('discounts'))
            ->withColumns('description', 'amount')
            ->useAutoIncrement();

        $fulfillment = (new Definition('orders_fulfillments', ['order_id', 'employee_id']));

        $item = (new Definition('items'))
            ->withColumns('id', 'description', 'amount', 'modified')
            ->withModificationData(['modified' => '2019-01-02 03:04:05']);


        $order = (new Definition('orders'))
            ->withColumns('id', 'date_created')
            ->withOne($discount, 'discount', 'order_id')
            ->withOne($fulfillment, 'fulfillment', 'order_id')
            ->withMany($item, 'items', 'order_id')
            ->withDeletionTimestamp('date_deleted');

        $customer = (new Definition('customers'))
            ->withColumns('id', 'name')
            ->withMany($order, 'orders', 'customer_id');

        return new Mapping($this->db, $customer, [], [
            'inserted' => [$this->hook],
            'updated' => [$this->hook],
            'removed' => [$this->hook]
        ]);
    }

    /**
     * Returns a new mapping for testing.
     *
     * @return Mapping
     */
    public function getReadOnlyMapping()
    {
        $item = (new Definition('items'))
            ->withColumns('id', 'description', 'amount')
            ->readOnly();

        $order = (new Definition('orders'))
            ->withColumns('id', 'date_created')
            ->withMany($item, 'items', 'order_id')
            ->withCreationData([
                'date_created' => gmdate('Y-m-d H:i:s'),
            ])
            ->withDeletionTimestamp('date_deleted');

        $customer = (new Definition('customers'))
            ->withColumns('id', 'name')
            ->withMany($order, 'orders', 'customer_id');

        return new Mapping($this->db, $customer);
    }
}
