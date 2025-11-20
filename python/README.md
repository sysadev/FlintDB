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

- Create and activate a virtual environment:
  ```bash
  python -m venv .venv
  source .venv/bin/activate
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

# Access the table
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
active_users = users_table.find({
    "is_active": True
})

# Access single row column
print(user["firstname"])

# Access row columns
print(dict(user))
# Or
for column, value in user:
    print(column, "=", value)

# Delete a row from table
user.delete()

# Delete table from database
users_table.delete()

# Delete entire database data
db.delete()
```


<!--
## Advanced Usage Example (Atomicity, Security, and Caching)

This demonstrates the core security, performance, and integrity features built into the system.

```python
from flintdb import Database

# Initialize the database (kek enables TDE)
db = Database(name="dbname", storage="./data_dir", kek="strong_secret_key")


# --- SCHEMA Definition ---

# Create "customers" table with schema and encrypted column
db.create_table(
    "customers",
    lambda schema:
        (
            schema
            .add("name", "@text", required=True)
            .add("email", "@text", required=True)
            .add("password", "@text", required=True)
            .add("address", "@text", required=True)
            .add("phone", "@text", required=True)
            .add("credit_card_number", "@text", required=True, encrypted=True)
        )
)

# Create "orders" table with schema
db.create_table(
    "orders",
    lambda schema:
        (
            schema
            .add("order_id", "@int", required=True)
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

# Access the tables
orders_table = db.table("orders")
customers_table = db.table("customers")


# --- Showcase Atomicity and Custom Caching ---

# Performance Check: Retrieving data is fast due to custom file cache
order = orders_table.find_one({"order_id": 5001})

# Transactional Update: Ensures integrity during a write operation
order.update({"status": "processing"})

print(f"Transaction handled successfully. Status: {order.column('status')}")


# --- Advanced QUERY ---
query = orders_table.query()
query.where("status", "=", "processing")
query.where("shipping_method", "!=", "N/A")
query.sort("order_date", "DESC")
query.limit(100)

result = query.fetch()


# --- BACKUP ---
from flintdb import Backup

# Create a backup of database
Backup.dump(database=db, file="./my_db_backup.zip")

# Restore a database backup
Backup.load(file="my_db_backup.zip")
```

## Running Tests

To ensure data integrity, run the built-in unit tests:

```bash
python -m unittest discover tests
```
-->

## 🔗 Back to Mono-Repo

For the full architectural goals and multi-language implementations, please visit the [main README](../README.md).
