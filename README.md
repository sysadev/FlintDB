# FlintDB

## 💡 What is FlintDB?

FlintDB is a lightweight NoSQL database engine I built to gain a low-level understanding of data systems. It uses flat files for storage and is designed to prove that reliable, complex logic can be built with minimal overhead.

This repository holds multiple, completed versions of the engine built in different languages, all developed using **Object-Oriented Programming (OOP)** principles.

---

## 🏗️ Core Features (The Logic)

This project focuses on proving two critical, complex data management principles:

1.  **Transactional Atomicity (Reliable Writes):** I built custom logic to ensure data is **fully saved or not saved at all**. This prevents file corruption caused by system failures during write operations.
2.  **Encryption:** I implemented a **Transparent Data Encryption (TDE)** system to secure the data files at rest without requiring extra steps from the user.

---

## 💻 Implementations

| Version | Status | Language Focus | Directory |
| :--- | :--- | :--- | :--- |
| **Python** | **Complete** | Focuses on modern OOP structure and efficiency. | `/python` |
| **PHP** | **Complete** | Focuses on core architectural design and data manipulation. | `/php` |
| **Java** | Planned | Future implementation for learning enterprise patterns. | `/java` |

---

## 🚀 How to Run It

### Prerequisites

You need:
- Python 3.10+
- PHP 8+

### Getting Started

1.  **Clone the Repo:**
    ```bash
    git clone https://github.com/sysadev/FlintDB.git
    cd FlintDB
    ```
2.  **Choose a Version:** Navigate to the `/python` or `/php` directory and follow the specific local instructions found within that folder's README.

---

## 🧑‍💻 Why I Built This

I built FlintDB to gain a hands-on, low-level understanding of core computer science principles like **data integrity** and **systems logic**. This experience has been invaluable for understanding complex backend architecture and expanding my multi-language development skills.
