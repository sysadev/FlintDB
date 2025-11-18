class Database:
    """This class serves as entry point for all database operations.

    Attributes:
        _name (str): The name of the database.
        _storage (str, optional): The filesystem path to the database parent directory.
        _kek (str, optional): The encryption key used for Transparent Data Encryption (TDE).
    """
    def __init__(self, name: str, storage: str = "", kek: str = "") -> None:
        """Initializes the Database object.

        Args:
            name: The name of the database
            storage: The directory path where database folder is stored
            kek: The secret key for Transparent Data Encryption (TDE)

        Raises:
            ValueError: If name contains invalid character
            NotADirectoryError: If storage directory does not exist
            OSError: If failed to create necessary folder and file
        """
        from pathlib import Path
        from .crypto import Crypto

        self._name = name
        self._storage = Path(storage or Path.cwd())
        self._kek = Crypto.kek(kek)

        if not self._name.isalnum():
            raise ValueError("Database name should contain only alphabets and numbers")
        elif not self._storage.is_dir():
            raise NotADirectoryError("Storage path is not a directory")

        folder = self.folder()
        if not folder.is_dir():
            try:
                folder.mkdir()
            except OSError as e:
                raise OSError(f"Failed to create database directory: {e}")

            from datetime import datetime
            from .version import __version__
            metadata = {
                "created": datetime.now().timestamp(),
                "version": __version__
            }

            from .file import File
            file = File(folder / ".metadata")
            if not file.write_json(metadata):
                self.delete()
                raise OSError("Failed to create database metadata")

    def name(self) -> str:
        """Returns name of the database"""
        return self._name

    def rename(self, new_name: str) -> bool:
        """Renames the database.

        This method takes a new name and renames the database.

        Args:
            new_name: The desired new name

        Returns:
            True if successfully renamed, False otherwise.
        """
        if not new_name.isalnum():
            raise ValueError("Database name should contain only alphabets and numbers")

        target_dir = self._storage / new_name
        if target_dir.is_dir():
            return False

        try:
            self.folder().replace(target_dir)
            return True
        except OSError:
            return False

    def folder(self) -> "Path":
        """Returns an instance of Path object, for the absolute path to the database directory."""
        return self.storage() / self._name

    def metadata(self, excess: bool = False) -> dict:
        """Returns database metadata.

        Args:
            excess: To get count of tables and total size of the database.
        """
        from .file import File

        folder = self.folder()
        file = File(folder / ".metadata")

        metadata = file.read_json()
        metadata["name"] = self._name
        metadata["modified"] = folder.stat().st_mtime
        metadata["tables"] = metadata["size"] = 0

        if not excess:
            return metadata

        for table in self.tables():
            table_metadata = table.metadata(excess=True)
            metadata["size"] += table_metadata["size"]
            metadata["tables"] += 1

        return metadata

    def delete(self) -> bool:
        """Deletes the database directory.

        Returns:
            True if successfully deleted, False otherwise.
        """
        try:
            import shutil
            shutil.rmtree(self.folder())
            return True
        except OSError:
            return False

    def kek(self) -> bytes | None:
        """Returns database encryption key."""
        return self._kek

    def storage(self) -> "Path":
        """Returns an instance of Path object, for the absolute path to the database parent directory."""
        return self._storage.absolute()

    def create_table(self, name: str, callback: "Callable" = lambda x: x) -> bool:
        """Creates new table.

        Args:
            name: The name of the table to create.
            callback: The callback to return the instance of Schema object.

        Returns:
            True if successfully, False otherwise.

        Raises:
            ValueError: If name contains invalid character.
            TypeError: If callback returns something other than instance of Schema.
            OSError: If failed to create necessary folder and file
        """
        if not name.isalnum():
            raise ValueError("Table name should contain only alphabets and numbers")

        folder = self.folder() / name
        if folder.is_dir():
            return False

        from .schema import Schema
        try:
            schema = callback(Schema())
        except:
            schema = Schema()

        if not isinstance(schema, Schema):
            raise TypeError("Invalid schema provided")

        has_encrypted_columns = schema.has_encrypted_columns()
        if has_encrypted_columns and not self._kek:
            raise ValueError("KEK is required to encrypt columns")

        try:
            folder.mkdir()
        except OSError as e:
            raise OSError(f"Failed to create table directory: {e}")

        if has_encrypted_columns:
            from .crypto import Crypto
            dek = Crypto.random_dek(self._kek)
        else:
            dek = ""

        schema.remove("_id")

        from datetime import datetime
        metadata = {
            "created": datetime.now().timestamp(),
            "schema": schema.sorted_schema(),
            "dek": dek
        }

        from .file import File
        file = File(folder / ".metadata")
        if not file.write_json(metadata):
            try:
                folder.rmdir()
                return False
            except OSError as e:
                raise OSError(f"Failed to create table metadata: {e}")

        return True

    def table(self, name: str) -> "Table":
        """Retrieves an instance of specific table within the database.

        Args:
            name: The name of the table to access.

        Returns:
            An instance of the request table object.
        """
        from .table import Table
        return Table(name, self)

    def tables(self, exclude: list = []) -> "Generator":
        """Yields instances of Table class for all tables within the database.

        Args:
            exclude: List of tables to exclude
        """
        for table in self.folder().iterdir():
            if table.name.isalnum() and table.name not in exclude:
                yield self.table(table.name)

    def query(self, table: str) -> "Query":
        """Returns Query instance for the given table.

        Args:
            table: The name of the table
        """
        from .query import Query
        query = Query(self)
        query.use_table(table)
        return query
