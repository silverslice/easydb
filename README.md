# EasyDb

[![Coverage Status](https://coveralls.io/repos/silverslice/easydb/badge.svg?branch=master&service=github)](https://coveralls.io/github/silverslice/easydb?branch=master)

EasyDb is a simple wrapper over standart myslqi extension.

What does this library offer you?

- Use placehoders with different types to pass data into query safely.
- Select one cell or all rows with only one method.
- Insert and update rows by passing column-value arrays.
- Use transactions with only one method.
- Executes multiple queries to load your sql dumps.
- Peforms multiple inserts and `insert... on duplicate update` queries without ton of code.

## Install

Use composer to install library.

`composer require silverslice/easydb`

## Connect to the database

```php
use Silverslice\EasyDb\Database;

// connect options
$options = [
    'host'     => 'localhost',
    'username' => 'root',
    'password' => '',
    'dbname'   => 'testdb',
    'charset'  => 'utf8'
];

// mysql options (not required)
$mysqlOptions = [MYSQLI_INIT_COMMAND => 'SET AUTOCOMMIT = 0'];

$db = new Database($options, $mysqlOptions);
```

## Placeholders

EasyDb uses placeholders to place data in the query.

### Types of placeholders

`?` - smart

In most cases you can use this placeholder. It automatically detects your value and correctly formats it.
- Integer value will be inserted as is.
- Null value will be inserted as NULL keyword.
- Database expressions will be inserted as is.
- In other cases value will be inserted as string.

`?i` - integer

Value will be cast to int with intval function.

`?s` - string

Value will be escaped with mysqli internal escape function and will be wrapped with single quote.

`?f` - float

Value will be cast to float with floatval function.

`?a` - array

This placeholder expects array, correctly escapes and quotes its values, and separates them by comma.
It is useful in queries like `SELECT * FROM table WHERE id IN (1, 2, 3)`

```php
$db->getAll('SELECT * FROM table WHERE id IN (?a)', [1, 2, 3]);

// generated sql: SELECT * FROM table WHERE id IN (1, 2, 3)
```

`?u` - set

This placeholder expects column-value array and generates code for SET clouse

```php
$db->query('UPDATE products SET ?u', ['key' => 'foo', 'value' => 1]);

// generated sql: UPDATE products SET `key` = 'foo', `value` = 1
```

`?e` - escape

Value will be escaped with mysqli internal escape function.

`?p` - part

Value will be inserted as is. Use this placeholder to insert already prepared part of sql query.

### Make query dinamically

If your query depends on some conditions you can use `prepare` method to replace all placeholders in string with passed parameters.
This string may be inserted into query safely.

```php
$pricePart = '';
if ($price) {
    $pricePart = $db->prepare(' AND price < ?', $price);
}

$db->getAll('SELECT * FROM products WHERE category_id = ? ?p', $categoryId, $pricePart);
```

## Exceptions

EasyDb converts all database errors to `\Silverslice\EasyDb\Exception`. Use `getQuery` method to get the query which caused an error.

**Example:**

```php
try {
    $this->db->query("SELECTT 1");
} catch (Exception $e) {
    // get error code
    $code = $e->getCode();

    // get query with error
    $query = $e->getQuery();

    // get error message: code, error and query
    $message = $e->getMessage();
}
```

## Selecting rows

### getOne
Peforms query and fetchs first cell from the first result row

*Parameters:*
- *string* $query - The SQL query
- *mixed* $param1 - Parameter for the first placeholder
- *mixed* $param2 - Parameter for the second placeholder
- ...

```php
getOne($query, $param1, $param2...)
```

*Returns:*
- *string|null*

### getAssoc
Peforms query and fetchs first result row as an associative array

```php
getAssoc($query, $param1, $param2...)
```

*Returns:*
- *array|null*

### getAll
Peforms query and fetchs all result rows as an associative array

```php
getAll($query, $param1, $param2...)
```

*Returns:*
- *array*

### getColumn
Peforms query and fetchs one column from the result set as an enumerate array

```php
getColumn($query, $param1, $param2...)
```

*Returns:*
- *array*

### getPairs
Peforms query and fetchs key-value pairs from the result set.

```php
getPairs($query, $param1, $param2...)
```

*Returns:*
- *array*

### getAllKeyed
Peforms query and fetchs key-values pairs from the result set.

```php
getAllKeyed($query, $param1, $param2...)
```

*Returns:*
- *array|bool*


## Modifying rows

### insert
Inserts row into table

```php
insert($table, $params, $ignore = false)
```

*Parameters:*
- *string* $table - Table name
- *array* $params - Column-value pairs
- *bool* $ignore - Use or not IGNORE keyword

*Returns:*
- *mixed* - Inserted row id or true if table hasn't autoincrement field

### update
Updates table rows

```php
update($table, $params, $where = array())
```

*Parameters:*
- *string* $table - Table name
- *array* $params - Column-value pairs
- *array* $where - UPDATE WHERE clause(s). Several conditions will be concatenated with AND keyword

*Returns:*
- *int* - The number of affected rows

### insertUpdate
Inserts or updates table row using INSERT... ON DUPLICATE KEY UPDATE clause

```php
insertUpdate($table, $insert, $update = array())
```

*Parameters:*
- *string* $table - Table name
- *array* $insert - Column-value pairs to insert
- *array* $update - Column-value pairs to update if key already exists in table

*Returns:*
- *int* - The number of affected rows: 1 if row was inserted or 2 if row was updated

### multiInsert
Inserts multiple rows into table

```php
multiInsert($table, $fields, $data, $ignore = false)
```

*Parameters:*
- *string* $table - Table name
- *array* $fields - Field names
- *array* $data - Two-dimensional array with data to insert
- *bool* $ignore - Use or not IGNORE keyword

*Returns:*
- *int* - The number of affected rows

### delete
Deletes table rows

```php
delete($table, $where = array())
```

*Parameters:*
- *string* $table - Table name
- *array* $where - UPDATE WHERE clause(s). Several conditions will be concatenated with AND keyword

*Returns:*
- *int* - The number of affected rows

## Using transactions

### beginTransaction
Starts a transaction

```php
beginTransaction()
```

### commit
Commits the current transaction

```php
commit()
```

### rollback
Rolls back current transaction

```php
rollback()
```

### transaction
Runs code in transaction

```php
transaction($process)
```

*Parameters:*
- *callable* $process - Callback to process

*Returns:*
- *bool* - True if transaction was successful commited, false otherwise

*Throws:*

- *\Silverslice\EasyDb\Exception*

**Example:**
```php
$this->db->transaction(function () {
    $this->db->query("INSERT INTO test (id, code, price) VALUES (3003, '1', 1)");
    $this->db->query("INSERT INTO test (id, code, price) VALUES (3004, '1', 1)");
});
```


## Advanced

### Expressions

Columns in SQL queries are sometimes expressions, not simply column names from a table. You can create an object of type Expression to insert expression into sql.

```php
$this->db->insert('test', [
    'time' => new Expression('NOW()')
]);

// or use expression alias

$this->db->insert('test', [
    'time' => $this->db->expression('NOW()')
]);
```

### Multi queries

Use `multiQuery` method to execute several queries which are concatenated by a semicolon, for example to load sql dump from file.

#### multiQuery
Executes one or multiple queries which are concatenated by a semicolon

```php
multiQuery($queries)
```

*Parameters:*
- *string* $queries

*Returns:*
- *bool*

*Throws:*

- *\Silverslice\EasyDb\Exception*

**Example:**
```php
$this->db->multiQuery("
    DROP TABLE IF EXISTS `test`;
    CREATE TABLE `test` (
     `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
     `code` char(15) NOT NULL,
     `name` varchar(200) NOT NULL,
     `price` decimal(10,2) unsigned DEFAULT NULL,
     `order` int(11) unsigned DEFAULT 0,
     PRIMARY KEY (`id`),
     KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    INSERT INTO test (id, code, name, price)
    VALUES
      (1, '001', 'Cup', 20.00),
      (2, '002', 'Plate', 30.50)
");
```
