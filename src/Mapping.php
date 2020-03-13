<?php

namespace PicoMapper;

use PicoDb\Database;
use PicoDb\Table;

class Mapping extends Table
{
    /**
     * @var Definition
     */
    protected $definition;

    /**
     * @var string[]
     */
    protected $columns = [];

    /**
     * @var callable[]
     */
    protected $hooks = [];

    /**
     * @var int|null
     */
    protected $lastId;

    /**
     * Mapping constructor.
     *
     * @param Database   $db
     * @param Definition $definition
     * @param array      $columns
     * @param callable[] $hooks
     */
    public function __construct(Database $db, Definition $definition, array $columns = [], array $hooks = [])
    {
        $this->definition = $definition;
        $this->columns = $columns;
        $this->hooks = $hooks;

        parent::__construct($db, $definition->getTable());
    }

    /**
     * Fetches and maps a single record.
     *
     * @return array|mixed|null
     */
    public function findOne()
    {
        if ($this->definition->getDeletionTimestamp()) {
            $this->isNull($this->definition->getDeletionTimestamp());
        }

        $this->columns(...$this->buildColumns());
        $this->limit(1);
        $records = parent::findAll();

        if (empty($records)) {
            return null;
        }

        $mapped = $this->map($records);
        return array_shift($mapped);
    }

    /**
     * Fetches and maps all records.
     *
     * @return array
     */
    public function findAll()
    {
        if ($this->definition->getDeletionTimestamp()) {
            $this->isNull($this->definition->getDeletionTimestamp());
        }

        $this->columns(...$this->buildColumns());
        return $this->map(parent::findAll());
    }

    /**
     * Maps the provided array into the database.
     *
     * @param array $data
     * @return boolean
     */
    public function insert(array $data)
    {
        $base = array_merge(
            $this->getBaseData($data),
            $this->definition->getCreationData()
        );

        if ($this->definition->isReadOnly()) {
            return true;
        }

        $useTransaction = !$this->db->getConnection()->inTransaction();

        if ($useTransaction) {
            $this->db->startTransaction();
        }

        if ($this->definition->isAutoIncrement()) {
            // Force the database to assign sequence numbers
            unset($base[$this->definition->getPrimaryKey()[0]]);
        }

        if (!parent::insert($base)) {
            // Transaction already cancelled by the statement handler
            return false;
        }

        if ($this->definition->isAutoIncrement()) {
            $this->lastId = $this->db->getLastId();
            $data[$this->definition->getPrimaryKey()[0]] = $this->lastId;
        }

        foreach ($this->definition->getProperties() as $property) {
            if ($property->getDefinition()->isReadOnly()) {
                continue;
            }

            $items = $data[$property->getName()] ?? [];

            if (!$property->isCollection()) {
                if (empty($items)) {
                    continue;
                }

                $items = [$items];
            }

            $mapping = new static($this->db, $property->getDefinition(), [$property->getForeignColumn()]);

            foreach ($items as $item) {
                $item[$property->getForeignColumn()] = $data[$property->getLocalColumn()];

                if (!$mapping->insert($item)) {
                    // Transaction already cancelled by the statement handler
                    return false;
                }
            }
        }

        if ($useTransaction) {
            $this->db->closeTransaction();
        }

        $this->dispatch('inserted', $data, [
            $data
        ]);

        return true;
    }

    /**
     * Maps the provided array into the database.
     *
     * @param array $data
     * @return boolean
     */
    public function update(array $data = array())
    {
        $primaryKey = $this->definition->getPrimaryKey();

        if ($this->definition->isReadOnly()) {
            return true;
        }

        foreach ($primaryKey as $column) {
            if (!array_key_exists($column, $data)) {
                return false;
            }

            if (is_null($data[$column])) {
                $this->isNull($column);
            } else {
                $this->eq($column, $data[$column]);
            }
        }

        if (!$original = $this->findOne()) {
            return false;
        }

        $useTransaction = !$this->db->getConnection()->inTransaction();

        if ($useTransaction) {
            $this->db->startTransaction();
        }

        try {
            $deleteIds = $this->replace($data, $original);
            $this->delete($deleteIds);

            if ($useTransaction) {
                $this->db->closeTransaction();
            }

            $this->dispatch('updated', $data, [
                $data,
                $original
            ]);

            return true;
        } catch (\Exception $e) {
            if ($useTransaction) {
                $this->db->cancelTransaction();
            }

            return false;
        }
    }

    /**
     * Maps the provided array into the database.
     *
     * @param array $data
     * @return bool
     */
    public function save(array $data)
    {
        $primaryKey = $this->definition->getPrimaryKey();

        foreach ($primaryKey as $column) {
            if (!array_key_exists($column, $data)) {
                return false;
            }

            if (is_null($data[$column])) {
                $this->isNull($column);
            } else {
                $this->eq($column, $data[$column]);
            }
        }

        $original = $this->findOne();
        return $original ? $this->update($data) : $this->insert($data);
    }

    /**
     * Removes data matching the condition.
     *
     * @return bool
     */
    public function remove()
    {
        $data = $this->findAll();
        $ids = [];

        foreach ($data as $original) {
            $ids = $this->collectPrimary($original, $ids);
        }

        try {
            $this->db->startTransaction();
            $this->delete($ids);

            $this->db->closeTransaction();

            foreach ($data as $item) {
                $this->dispatch('removed', $item);
            }

            return true;
        } catch (\Exception $exception) {
            $this->db->cancelTransaction();
            return false;
        }
    }

    /**
     * Replaces existing data in the database and returns IDs for
     * deletion.
     *
     * @param array $data
     * @param array $original
     * @param array $deleteIds
     * @return array
     * @throws \Exception
     */
    private function replace(array $data, array $original, array $deleteIds = [])
    {
        $primaryKey = $this->definition->getPrimaryKey();

        $query = $this
            ->db
            ->table($this->definition->getTable());

        foreach ($primaryKey as $column) {
            if (!array_key_exists($column, $data)) {
                return $deleteIds;
            }

            if (is_null($data[$column])) {
                $query->isNull($column);
            } else {
                $query->eq($column, $data[$column]);
            }
        }

        $base = $this->getBaseData($data);
        $originalBase = $this->getBaseData($original);

        if (!empty(array_diff_assoc($base, $originalBase))) {
            $base = array_merge(
                $base,
                $this->definition->getModificationData()
            );

            if (!$query->update($base)) {
                throw new \Exception('Failed to update record.');
            }
        }

        foreach ($this->definition->getProperties() as $property) {
            if ($property->getDefinition()->isReadOnly()) {
                continue;
            }

            $propertyPrimary = $property->getDefinition()->getPrimaryKey();
            $propertyData = $data[$property->getName()] ?? [];
            $propertyOriginal = $original[$property->getName()] ?? [];

            if (!$property->isCollection()) {
                $propertyData = [$propertyData];
                $propertyOriginal = [$propertyOriginal];
            }

            $mapping = new static($this->db, $property->getDefinition(), [$property->getForeignColumn()]);

            $insert = Collection::diffByKeys($propertyData, $propertyOriginal, $property->getDefinition()->getPrimaryKey());
            $delete = Collection::diffByKeys($propertyOriginal, $propertyData, $property->getDefinition()->getPrimaryKey());
            $update = Collection::intersectByKeys($propertyData, $propertyOriginal, $property->getDefinition()->getPrimaryKey());

            foreach ($insert as $item) {
                if (!$property->getJoinTable()) {
                    $item[$property->getForeignColumn()] = $data[$property->getLocalColumn()];
                }

                if (!$mapping->insert($item)) {
                    throw new \Exception('Failed to insert record.');
                }
            }

            foreach ($delete as $item) {
                $deleteIds = $mapping->collectPrimary($item, $deleteIds);
            }

            foreach ($update as $item) {
                if (!$property->getJoinTable()) {
                    $item[$property->getForeignColumn()] = $data[$property->getLocalColumn()];
                }

                if (!$property->isCollection()) {
                    $deleteIds = $mapping->replace($item, reset($propertyOriginal), $deleteIds);
                    continue;
                }

                $originalItem = Collection::first($propertyOriginal, function($original) use ($item, $propertyPrimary) {
                    foreach ($propertyPrimary as $column) {
                        if ($original[$column] != $item[$column]) {
                            return false;
                        }
                    }

                    return true;
                });

                $deleteIds = $mapping->replace($item, $originalItem, $deleteIds);
            }
        }

        return $deleteIds;
    }

    /**
     * Deletes records identified by the provided associative array
     * mapping tables to primary key values.
     *
     * @param array $ids
     * @throws \Exception
     */
    private function delete($ids = [])
    {
        foreach ($ids as $table => $deleteColumns) {
            foreach ($deleteColumns as $deletion => $primaries) {
                // Arrange values into groups based on all but the last key
                $grouped = Collection::group($primaries, function($keys) {
                    array_pop($keys);
                    return implode(':', $keys);
                });

                // Delete by grouping
                foreach ($grouped as $group) {
                    $query = $this
                        ->db
                        ->table($table);

                    $first = reset($group);

                    // Determine column to use for IN condition
                    end($first);
                    $primary = key($first);

                    foreach ($first as $column => $value) {
                        if ($column !== $primary) {
                            $query->eq($column, $value);
                        }
                    }

                    $query->in($primary, array_column($group, $primary));

                    $result = $deletion ? $query->update([$deletion => gmdate('Y-m-d H:i:s')]) : $query->remove();
                    if (!$result) {
                        throw new \Exception('Failed to delete records.');
                    }
                }
            }
        }
    }

    /**
     * Returns an associative array mapping table names to primary keys
     * constructed by recursively scanning data.
     *
     * @param array $data
     * @param array $list
     * @return array
     */
    private function collectPrimary(array $data = [], $list = [])
    {
        $table = $this->definition->getTable();
        $primaryKey = $this->definition->getPrimaryKey();
        $deletion = $this->definition->getDeletionTimestamp();

        $item = [];

        foreach ($primaryKey as $column) {
            $item[$column] = $data[$column];
        }

        $list[$table][$deletion][] = $item;

        foreach ($this->definition->getProperties() as $property) {
            $values = $data[$property->getName()] ?? [];

            if (!$property->isCollection()) {
                if (empty($values)) {
                    continue;
                }

                $values = [$values];
            }

            foreach ($values as $value) {
                $mapping = new static($this->db, $property->getDefinition());
                $list = $mapping->collectPrimary($value, $list);
            }
        }

        return $list;
    }

    /**
     * Maps the provided data onto an array.
     *
     * @param array $data
     * @return array
     */
    private function map(array $data)
    {
        if (empty($data)) {
            return [];
        }

        $properties = [];
        foreach ($this->definition->getProperties() as $property) {
            $mapping = new static($this->db, $property->getDefinition(), [$property->getForeignColumn()]);

            if ($property->getJoinTable()) {
                $mapping->columns[] = sprintf('%s.%s', $property->getJoinTable(), $property->getJoinForeignColumn());
                $mapping
                    ->join($property->getJoinTable(), $property->getJoinLocalColumn(), $property->getForeignColumn())
                    ->in(sprintf('%s.%s', $property->getJoinTable(), $property->getJoinForeignColumn()), array_column($data, $property->getLocalColumn()));
                $groupColumn = $property->getJoinForeignColumn();
            } else {
                $mapping->in($property->getForeignColumn(), array_column($data, $property->getLocalColumn()));
                $groupColumn = $property->getForeignColumn();
            }

            $results = $mapping
                ->findAll();

            $properties[$property->getName()] = Collection::group($results, function($result) use ($groupColumn) {
                return $result[$groupColumn];
            });
        }

        $mapped = [];
        foreach ($data as $item) {
            foreach ($this->definition->getProperties() as $property) {
                $value = $properties[$property->getName()][$item[$property->getLocalColumn()]] ?? [];
                $value = array_values($value);

                if (!$property->isCollection()) {
                    $value = array_shift($value);
                }

                $item[$property->getName()] = $value;
            }

            $mapped[] = $item;
        }

        return $mapped;
    }

    /**
     * Returns a copy of the provided data containing only the columns
     * present in the mapping's definition.
     *
     * @param array $data
     * @return array
     */
    private function getBaseData(array $data)
    {
        $columns = array_merge(
            $this->columns,
            $this->definition->getColumns(),
            $this->definition->getPrimaryKey()
        );

        foreach ($this->definition->getProperties() as $property) {
            $columns[] = $property->getLocalColumn();
        }

        return array_intersect_key($data, array_flip($columns));
    }

    /**
     * Returns an array of columns required by the mapping.
     *
     * @return array
     */
    private function buildColumns()
    {
        $columns = $this->definition->getPrimaryKey();
        $required = array_merge($this->definition->getColumns(), $this->columns);

        foreach ($this->definition->getProperties() as $item) {
            $required[] = $item->getLocalColumn();
        }

        foreach (array_unique($required) as $column) {
            if (strpos($column, '.') === false) {
                $columns[] = sprintf('%s.%s', $this->definition->getTable(), $column);
            } else {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * Dispatches an event to registered hooks.
     *
     * @param string $event
     * @param array  $data
     * @param array  $args
     */
    private function dispatch(string $event, array $data, $args = [])
    {
        if (empty($this->hooks[$event])) {
            return;
        }

        // Add primary keys to arguments
        array_unshift(
            $args,
            array_intersect_key($data, array_flip($this->definition->getPrimaryKey()))
        );

        // Add table to arguments
        array_unshift($args, $this->definition->getTable());

        // Call all registered hooks
        foreach ($this->hooks[$event] as $hook) {
            call_user_func_array($hook, $args);
        }
    }
}
