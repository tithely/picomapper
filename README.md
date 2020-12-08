PicoMapper
==========

PicoMapper is a minimalist data mapper built on PicoDb.

![Run Tests](https://github.com/tithely/picomapper/workflows/Run%20Tests/badge.svg)

Features
--------

- Built on [PicoDb](https://github.com/elvanto/picodb)
- No configuration files
- License: MIT

Requirements
------------

- PHP >= 7.3
- PDO extension
- Sqlite, Mssql, Mysql or Postgresql

Documentation
-------------

### Installation

```bash
composer require tithely/picomapper
```

### Setup

```php
use PicoDb\Database;
use PicoMapper\Mapper;
use PicoMapper\Definition;

$db = new Database([...]);
$mapper = new Mapper($db);
```

### Concepts

#### Definition

In order to map to or from the database, a mapping must be built from a definition. A definition consists of a table, a
primary key (one or more columns that uniquely identifies a record), columns, children (other definitions) and special
options that control things like what is considered a deleted record.

Consider a blog system with posts, comments and authors. A post has one author and many comments, a comment has one
author. Assuming posts, comments and authors have their own tables, a suitable definition would look like the following:

```php
$author = (new Definition('authors'))
    ->withColumns('name')
    ->withDeletionTimestamp('deleted');

$comment = (new Definition('comments'))
    ->withColumns('content')
    ->withOne($author, 'author', 'id', 'author_id')
    ->withDeletionTimestamp('deleted');

$post = (new Definition('posts'))
    ->withColumns('title', 'content')
    ->withOne($author, 'author', 'id', 'author_id')
    ->withMany($comment, 'comments', 'post_id')
    ->withDeletionTimestamp('deleted');
    
$mapping = $mapper->mapping($post);

```

The second constructor argument for `Definition` is an array of columns that uniquely identify a record within the table.
By default it's `['id']`.

Data to be set on insert and update can be set by calling `withCreationData()` and `withModificationData()` respectively. For example if you wanted to add a `date_entered` and `date_modified` columns to the `$post` definition, you would enter it as follows:
```php
$post = (new Definition('posts'))
    ->withColumns('title', 'content')
    ->withOne($author, 'author', 'id', 'author_id')
    ->withMany($comment, 'comments', 'post_id')
    ->withCreationData(['date_entered' => gmdate('Y-m-d G:i:s')])
    ->withModificationData(['date_modified' => gmdate('Y-m-d G:i:s')]);
    ->withDeletionTimestamp('deleted');
```

#### Mapping

Mappings have the same interface as PicoDb's `Table` class. That is, you can chain conditions and call `findOne()` or
`findAll()` to fetch records or `insert()`, `update()`, `remove()` and `save()` to modify records. In all cases, the
definition will be used to intelligently return or accept a structured array.

In the example above you could fetch or save a post using the following structured array, and all table relationships
will be followed automatically.

```php
$post = [
    'id' => 'abc123',
    'author' => [
        'id' => 'zxy321',
        'name' => 'John Doe'
    ],
    'title' => 'Data Mappers Rock',
    'content' => 'They save you time',
    'comments' => [
        [
            'id' => 'def456',
            'post_id' => 'abc123',
            'author' => [
                'id' => 'zxy321',
                'name' => 'John Doe'
            ],
            'content' => 'Did you like my post?'
        ],
        [
            'id' => 'hij789',
            'post_id' => 'abc123',
            'author' => [
                'id' => 'klm012',
                'name' => 'Jane Doe'
            ],
            'content' => 'Nice article!'
        ],
    ]
];

$mapper->save($post);
$saved = $mapper->eq('id', 'abc123')->findOne();

// $saved will be identical in structure to post
```

#### Hooks

Hooks are callbacks that can be triggered when a mapping performs the successful insert, update or removal of a record.
A hook registered against a mapper will be used for all top level mappings it creates.

```php
$mapper->registerHook('updated', function ($table, $key, $updated, $original) {
    printf('Table %s (ID: %s) was updated...', $table, implode(':', $key));
});
```
