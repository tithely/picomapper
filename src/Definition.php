<?php

namespace PicoMapper;

class Definition
{
    /**
     * @var string
     */
    private $table;

    /**
     * @var string[]
     */
    private $primaryKey = [];

    /**
     * @var bool
     */
    private $autoIncrement = false;

    /**
     * @var bool
     */
    private $readOnly = false;

    /**
     * @var string[]
     */
    private $columns = [];

    /**
     * @var Property[]
     */
    private $properties = [];

    /**
     * @var string|null
     */
    private $deletionTimestamp;

    /**
     * @var array
     */
    private $creationData = [];

    /**
     * @var array
     */
    private $modificationData = [];

    /**
     * Definition constructor.
     *
     * @param string   $table
     * @param string[] $primaryKey
     */
    public function __construct(string $table, array $primaryKey = ['id'])
    {
        $this->table = $table;
        $this->primaryKey = $primaryKey;
    }

    /**
     * Configures the primary key to use auto increment.
     *
     * @return Definition
     */
    public function useAutoIncrement()
    {
        if (count($this->primaryKey) > 1) {
            throw new \LogicException('Auto increment can only be used for non-composite primary keys.');
        }

        $this->autoIncrement = true;
        return $this;
    }

    /**
     * Sets read-only mode to true.
     *
     * @return Definition
     */
    public function readOnly()
    {
        $this->readOnly = true;
        return $this;
    }

    /**
     * Adds columns to be mapped.
     *
     * @param string ...$columns
     * @return Definition
     */
    public function withColumns(string ...$columns)
    {
        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    /**
     * Adds a one-to-one relationship.
     *
     * @param Definition $definition
     * @param string     $name
     * @param string     $foreignColumn
     * @param string     $localColumn
     * @return Definition
     */
    public function withOne(Definition $definition, string $name, string $foreignColumn, string $localColumn = 'id')
    {
        $this->properties[] = new Property($name, false, $definition, $localColumn, $foreignColumn);
        return $this;
    }

    /**
     * Adds a one-to-many relationship.
     *
     * @param Definition $definition
     * @param string     $name
     * @param string     $foreignColumn
     * @param string     $localColumn
     * @return Definition
     */
    public function withMany(Definition $definition, string $name, string $foreignColumn, string $localColumn = 'id')
    {
        $this->properties[] = new Property($name, true, $definition, $localColumn, $foreignColumn);
        return $this;
    }

    /**
     * Adds a one-to-many relationship through a joined table.
     *
     * @param Definition $definition
     * @param string $name
     * @param string $foreignColumn
     * @param string $localColumn
     * @param string $joinTable
     * @param string $joinForeignColumn
     * @param string $joinLocalColumn
     * @return Definition
     */
    public function withManyByJoin(Definition $definition, string $name, string $foreignColumn, string $localColumn, string $joinTable, string $joinForeignColumn, string $joinLocalColumn)
    {
        $property = new Property($name, true, $definition, $localColumn, $foreignColumn);
        $property->join($joinTable, $joinLocalColumn, $joinForeignColumn);

        $this->properties[] = $property;
        return $this;
    }

    /**
     * Sets the timestamp column used to signify if a record is deleted.
     *
     * @param string $column
     * @return Definition
     */
    public function withDeletionTimestamp(string $column)
    {
        $this->deletionTimestamp = $column;
        return $this;
    }

    /**
     * Sets an array of table data to be included when a record is inserted.
     *
     * @param array $data
     * @return Definition
     */
    public function withCreationData(array $data)
    {
        $this->creationData = $data;
        return $this;
    }

    /**
     * Sets an array of table data to be included when a record is modified.
     *
     * @param array $data
     * @return Definition
     */
    public function withModificationData(array $data)
    {
        $this->modificationData = $data;
        return $this;
    }

    /**
     * Returns the definition's base table.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Returns the base table's primary key.
     *
     * @return string[]
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * Returns the definition's readonly status.
     *
     * @return bool
     */
    public function isReadOnly()
    {
        return $this->readOnly;
    }

    /**
     * Returns true if the primary key is configured for auto increment.
     *
     * @return bool
     */
    public function isAutoIncrement()
    {
        return $this->autoIncrement;
    }

    /**
     * Returns the definition's columns.
     *
     * @return string[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Returns the definition's relationships.
     *
     * @return Property[]
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Returns the name of the timestamp column used to signify if a record is deleted,
     * otherwise null.
     *
     * @return null|string
     */
    public function getDeletionTimestamp()
    {
        return $this->deletionTimestamp;
    }

    /**
     * Returns an array of table data to be included when a record is inserted.
     *
     * @return array
     */
    public function getCreationData()
    {
        return $this->creationData;
    }

    /**
     * Returns an array of table data to be included when a record is modified.
     *
     * @return array
     */
    public function getModificationData()
    {
        return $this->modificationData;
    }
}
