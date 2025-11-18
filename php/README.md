# FlintDB PHP Implementation

## 🐘 Overview

This directory contains the complete **Object-Oriented Programming (OOP)** implementation of the FlintDB engine in PHP.

This version demonstrates:
- Adherence to PHP OOP best practices (classes, namespaces, dependency handling).
- The successful implementation of a full Database → Table → Row → Column structured data model.
- Efficient file I/O operations for data manipulation and storage.


## 🏗️ Core Architecture Highlights

This project was developed as a system study focusing on the implementation of:
- **Transactional Atomicity:** Logic to prevent file corruption during write operations.
- **Transparent Data Encryption (TDE):** Custom encryption classes for data security.
- **Custom Caching:** Performance optimization using a built-in file-based cache.


## 🚀 Getting Started

### Prerequisites

You need PHP 8+ installed on your system.


<!--
### Installation

- Navigate to this directory:
  ```bash
  cd php
  ```
- Use Composer (Recommended):
   composer install
-->


## Basic Usage Example (Initialization & Simple Write)

This shows the minimal steps required to initialize the database and insert a structured record.

```php
require 'flintdb/Autoload.php';

use FlintDB\Database;

// Initialize the database
$db = new Database( 'dbname', __DIR__ . '/data_dir' );

// Create a specific table
$db->create_table( 'users' );

// Access the table
$users_table = $db->table( 'users' );

// Simple row insertion
$users_table->insert([
  'user_id' => 101,
  'firstname' => 'John',
  'lastname' => 'Doe',
  'username' => 'johndoe',
  'email' => 'john@example.com',
  'password' => '$2y$12$HrMOTq0IVbCr/lRJ7TeEI.nPYEuZ/aNws1YnLHrxniVNVu5D3k4By',
  'created_at' => 1763123066,
  'is_active' => true
});

# Find single row
$user = $users_table->find_one([
  'username' => 'johndoe'
]);

# Find many rows
$active_users = $users_table->find([
  'is_active' => true
]);

# Access single row column
echo $user[ 'name' ];

# Access row columns
print_r( $user->columns() )
# Or
foreach ( $user as $column => $value ) {
  echo $column, '=', $value, PHP_EOL;
}

# Delete a row from table
$user->delete();

# Delete table from database
$users_table->delete();

# Delete entire database data
$db->delete();
```

<!--
## Advanced Usage Example (Security, Performance, and Atomicity)

This demonstrates the core security, performance, and integrity features built into the system.

```php
require 'flintdb/Autoload.php';

use FlintDB\Database;

$db = new Database( 'dbname', __DIR__ . '/data_dir' );
$db->create_table( 'orders' );


$orders_table = $db->table( 'orders' );

// --- Showcase Atomicity and Custom Caching ---

// Performance Check: Retrieving data is fast due to custom file cache
$order = $orders_table->find_one([ 'order_id' => 5001 ]);

// Transactional Update: Ensures integrity during a write operation
$order->update([ 'status' => 'processing' ]); 

echo 'Transaction handled successfully. Status: ' . $order->column( 'status' ) . '\n';
```


## Running Tests

To ensure data integrity, run the built-in unit tests (using PHPUnit or similar tool):

```bash
vendor/bin/phpunit tests
```
-->

## 🔗 Back to Mono-Repo

For the full architectural goals and multi-language implementations, please visit the [main README](../README.md).
