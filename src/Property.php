<?php

namespace PicoMapper;

class Property
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $collection = false;

    /**
     * @var Definition
     */
    private $definition;

    /**
     * @var string
     */
    private $localColumn;

    /**
     * @var string
     */
    private $foreignColumn;

    /**
     * @var string|null
     */
    private $joinTable;

    /**
     * @var string|null
     */
    private $joinLocalColumn;

    /**
     * @var string|null
     */
    private $joinForeignColumn;

    /**
     * Property constructor.
     *
     * @param string     $name
     * @param bool       $collection
     * @param Definition $definition
     * @param string     $localColumn
     * @param string     $foreignColumn
     */
    public function __construct(string $name, bool $collection, Definition $definition, string $localColumn, string $foreignColumn)
    {
        $this->name = $name;
        $this->collection = $collection;
        $this->definition = $definition;
        $this->localColumn = $localColumn;
        $this->foreignColumn = $foreignColumn;
    }

    /**
     * Adds a join to the definition.
     *
     * @param string $table
     * @param string $localColumn
     * @param string $foreignColumn
     */
    public function join(string $table, string $localColumn, string $foreignColumn)
    {
        $this->joinTable = $table;
        $this->joinLocalColumn = $localColumn;
        $this->joinForeignColumn = $foreignColumn;
    }

    /**
     * Returns the property's name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns true if the property is a collection.
     *
     * @return bool
     */
    public function isCollection()
    {
        return $this->collection;
    }

    /**
     * Returns the definition used to fetch the property.
     *
     * @return Definition
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * Returns the local column name.
     *
     * @return string
     */
    public function getLocalColumn()
    {
        return $this->localColumn;
    }

    /**
     * Returns the foreign column name.
     *
     * @return string
     */
    public function getForeignColumn()
    {
        return $this->foreignColumn;
    }

    /**
     * Returns the join table.
     *
     * @return string|null
     */
    public function getJoinTable()
    {
        return $this->joinTable;
    }

    /**
     * Returns the join's local column.
     *
     * @return string|null
     */
    public function getJoinLocalColumn()
    {
        return $this->joinLocalColumn;
    }

    /**
     * Returns the join's foreign column.
     *
     * @return string|null
     */
    public function getJoinForeignColumn()
    {
        return $this->joinForeignColumn;
    }
}
