<?php

namespace PicoMapper;

use PicoDb\Database;

class MapperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @var Database
     */
    private $db;

    public function setUp()
    {
        $this->db = new Database(['driver' => 'sqlite', 'filename' => ':memory:']);
        $this->mapper = new Mapper($this->db);
    }

    public function tearDown()
    {
        $this->mapper = null;
        $this->db = null;
    }

    public function testMapping()
    {
        $definition = new Definition('posts');
        $mapping = $this->mapper->mapping($definition);

        $this->assertInstanceOf(Mapping::class, $mapping);
    }
}

