# FlintDB Python Implementation

## 🐍 Overview

This directory contains the complete **Object-Oriented Programming (OOP)** implementation of the FlintDB engine in Python.

This version demonstrates:
- The successful implementation of a full Database → Table → Row → Column structured data model.
- Clean, Pythonic coding practices (PEP 8) with detailed class structures for data models.
- Application of Python's I/O capabilities for high-performance file operations.


## 🏗️ Core Architecture Highlights

This project was developed as a system study focusing on the implementation of:
- **Transactional Atomicity:** Logic to prevent file corruption during write operations.
- **Transparent Data Encryption (TDE):** Custom encryption classes for data security.
- **Custom Caching:** Performance optimization using a built-in file-based cache.


## 🚀 Getting Started

### Prerequisites

You need Python 3.10+ installed on your system.


### Installation

- Navigate to this directory:
  ```bash
  cd python
  ```
- Install dependencies:
  ```bash
  pip install -r requirements.txt
  ```


## Basic Usage Example (Initialization & Simple Write)

This shows the minimal steps required to initialize the database and store a simple record.

```python
from flintdb import Database

# Initialize the database
db = Database(name="dbname", storage="./data_dir")

# Create a specific table
db.create_table(name="users")

# Access a specific table
users_table = db.table("users")

# Simple row insertion
users_table.insert({
    "user_id": 101,
    "firstname": "John",
    "lastname": "Doe",
    "username": "johndoe",
    "email": "john@example.com",
    "password": "$2y$12$HrMOTq0IVbCr/lRJ7TeEI.nPYEuZ/aNws1YnLHrxniVNVu5D3k4By",
    "created_at": 1763123066,
    "is_active": true
})

# Find single row
user = users_table.find_one({
    "username": "johndoe"
})

# Find many rows
users = users_table.find({
    "is_active": True
})
```


## Advanced Usage Example (Atomicity, Security, and Caching)

This demonstrates the core security, performance, and integrity features built into the system.

```bash
from flintdb import Database

# Initialize the database (kek enables TDE)
db = Database(name="dbname", storage="./data_dir", kek="strong_secret_key")


# --- SCHEMA ---

# Create a table with schema
db.create_table(
    "orders",
    lambda schema:
        (
            schema
            .add("customer_id", "@text", required=True)
            .add("order_date", "@text", required=True)
            .add("status", "@enum", enum_values=["Pending", "Processing", "Shipped", "Complete", "Cancelled"], required=True)
            .add("shipping_method", "@enum", enum_values=["Standard", "Express", "N/A"], required=True)
            .add("total_amount", "@float", required=True)
            .add("product_id", "@text", required=True)
            .add("quantity", "@int", required=True)
            .add("unit_price", "@float", required=True)
        )
)

# Access the table
orders_table = db.table('orders')



# --- Showcase Atomicity and Custom Caching ---

# Transactional Update: Ensures integrity during a write operation
orders_table.update_row('order_id', 5001, 'status', 'processing') 

# Performance Check: Retrieving data is fast due to custom file cache
cached_order = orders_table.get_row('order_id', 5001)

print(f"Transaction handled successfully. Status: {cached_order.get('status')}")


# --- QUERY ---
query = db.query()


# --- BACKUP ---
from flintdb import Backup

# Create a backup
Backup.dump(database=db, file="my_db_backup.zip")

# Restore a backup
Backup.load(file="my_db_backup.zip")
```


## Running Tests

To ensure data integrity, run the built-in unit tests:

```bash
python -m unittest discover tests
```


## 🔗 Back to Mono-Repo

For the full architectural goals and multi-language implementations, please visit the [main README](../README.md).
