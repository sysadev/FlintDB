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


### Installation

- Navigate to this directory:
  ```bash
  cd php
  ```
<!--
- Use Composer (Recommended):
   composer install
-->


## Basic Usage Example (Initialization & Simple Write)

This shows the minimal steps required to initialize the database and insert a structured record.

```php
require 'flintdb/Autoload.php';

use FlintDB\Database;

$db = new Database( 'dbname', __DIR__ . '/data_dir' );
$db->create_table( 'users' );

$users_table = $db->table( 'users' );

// Simple row insertion
$users_table->insert([
    'user_id' => 101,
    'username' => 'shuaibysb',
    'status' => 'active'
]);
```


## Advanced Usage Example (Security, Performance, and Atomicity)

This demonstrates the core security, performance, and integrity features built into the system.

```php
require 'flintdb/Autoload.php';

use FlintDB\Database;

$db = new Database( 'dbname', __DIR__ . '/data_dir' );
$db->create_table( 'users' );


table('orders');

// --- Showcase Atomicity and Custom Caching ---

// 1. Transactional Update: Ensures integrity during a write operation
$orders_table->update_row('order_id', 5001, 'status', 'processing'); 

// 2. Performance Check: Retrieving data is fast due to custom file cache
$cached_order = $orders_table->get_row('order_id', 5001);

echo "Transaction handled successfully. Status: " . $cached_order['status'] . "\n";
```


## Running Tests

To ensure data integrity, run the built-in unit tests (using PHPUnit or similar tool):

```bash
vendor/bin/phpunit tests
```


## 🔗 Back to Mono-Repo

For the full architectural goals and multi-language implementations, please visit the [main README](../README.md).
