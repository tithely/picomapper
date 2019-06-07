<?php

namespace PicoMapper;

use PicoDb\Database;
use PicoDb\Table;

class Mapper
{
    /**
     * @var Database
     */
    private $db;

    /**
     * @var array
     */
    private $hooks = [];

    /**
     * Mapper constructor.
     *
     * @param Database $db
     */
    function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Returns a mapping for the provided definition.
     *
     * @param Definition $definition
     * @return Mapping
     */
    public function mapping(Definition $definition)
    {
        return new Mapping($this->db, $definition, [], $this->hooks);
    }

    /**
     * Returns a table object.
     *
     * @param string $table
     * @return Table
     */
    public function table(string $table)
    {
        return $this->db->table($table);
    }

    /**
     * Begins a database transaction.
     *
     * @return bool
     */
    public function startTransaction()
    {
        return $this->db->startTransaction();
    }

    /**
     * Commits a database transaction.
     *
     * @return bool
     */
    public function closeTransaction()
    {
        return $this->db->closeTransaction();
    }

    /**
     * Returns database log messages.
     *
     * @return array
     */
    public function getLogMessages()
    {
        return $this->db->getLogMessages();
    }

    /**
     * Registers a new hook.
     *
     * @param string   $event
     * @param callable $hook
     */
    public function hook(string $event, callable $hook)
    {
        $this->hooks[$event][] = $hook;
    }
}
