class Table:
    """Manages data within a specific table, handling row insertion updates, and lookups.

    Attributes:
        _name (str): The name of the table.
        _database (Database): The parent Database instance.
        _schema (list): The table defined scheme.
        _dek (str): The Data Encryption Key (DEK) for the table.
    """
    def __init__(self, name: str, database: "Database") -> None:
        """Initializes the Table object.

        Args:
            name: The name of the table.
            database: The parent Database instance.

        Raises:
            ValueError: If name contains invalid character.
            NotADirectoryError: If table does not exist.
        """
        self._name = name
        self._database = database
        self._schema = None
        self._dek = None

        if not self._name.isalnum():
            raise ValueError("Table name should contain only alphabets and numbers")
        elif not self.folder().is_dir():
            raise NotADirectoryError("Table does not exists")

    def name(self) -> str:
        """Returns table name."""
        return self._name

    def rename(self, new_name: str) -> bool:
        """Renames table to a desired new name.

        Args:
            new_name: The desired new name.

        Returns:
            True if successfully renamed, False otherwise.

        Raises:
            ValueError: If the new name contains invalid character.
        """
        if not new_name.isalnum():
            raise ValueError("Table name should contain only alphabets and numbers")

        target_dir = self._database.storage() / name_name
        if target_dir.is_dir():
            return False

        try:
            self.folder().replace(target_dir)
            from .cache import Cache
            cache = Cache(self)
            cache.flush()
            self._name = new_name
            return True
        except OSError:
            return False

    def folder(self) -> "Path":
        """Returns an instance of Path object, for the absolute path to the table directory"""
        return self._database.folder() / self._name

    def metadata(self, excess: bool = False) -> dict:
        """Returns table metadata.

        Args:
            excess: To get count of rows and total table size.
        """
        from .file import File

        folder = self.folder()
        file = File(folder / ".metadata")

        metadata = file.read_json()
        metadata["name"] = self._name
        metadata["modified"] = folder.stat().st_mtime
        metadata["rows"] = metadata["size"] = 0
        metadata["database"] = self._database

        self._dek = metadata["dek"]
        self._schema = metadata["schema"]

        if not excess:
            return metadata

        for row in self.rows():
            row_metadata = row.metadata()
            metadata["size"] += row_metadata["size"]
            metadata["rows"] += 1

        return metadata

    def delete(self) -> bool:
        """Deletes table directory."""
        try:
            import shutil
            from .cache import Cache
            shutil.rmtree(self.folder())
            cache = Cache(self)
            cache.flush()
            return True
        except OSError:
            return False

    def dek(self) -> str:
        """Returns table Data Encryption Key (DEK)."""
        if self._dek == None:
            self.metadata()

        return self._dek

    def database(self) -> "Database":
        """Returns parent database of the table."""
        return self._database

    def schema(self) -> dict:
        """Returns defined schema for the table."""
        if self._schema == None:
            self.metadata()

        return self._schema

    def alter(self, callback: "Callable") -> bool:
        """Updates table defined schema.

        Args:
            callback: The callback to return the instance of Schema object.

        Returns:
            True if successfully updated, False otherwise.

        Raises:
            TypeError: If callback returns something other than instance of Schema object.
        """
        from .schema import Schema
        try:
            schema = callback(Schema())
        except:
            schema = Schema()

        if not isinstance(schema, Schema):
            raise TypeError("Invalid schema provided")

        folder = self.folder()
        file = File(folder / ".metadata")

        schema.remove("_id")
        metadata = file.read_json()
        metadata["schema"] = schema.sorted_schema()
        return file.write_json(metadata)

    def insert(self, columns: dict) -> bool:
        """Inserts row to the table.

        Args:
            columns: The row columns to insert.

        Returns:
            True if successful, False otherwise.

        Raises:
            ValueError:
                If columns are empty.
                Or, if _id column is given and does not match any row.
                Or, if one of the columns has invalid value.
                Or, if one of the columns has encryption but database has no KEK.
                Or, if wrong database KEK is given.
        """
        if not columns:
            raise ValueError("Columns cannot be empty")

        folder = self.folder()
        if columns.get("_id"):
            try:
                self.row(columns.get("_id"))
            except ValueError:
                raise ValueError("ID does not match any row")
        else:
            from .crypto import Crypto
            id = Crypto.random_id(8)
            while (folder / f"{id}.ndjson").is_file():
                id = Crypto.random_id(8)

            columns["_id"] = id

        metadata = self.metadata()
        file = folder / f"{id}.ndjson"
        if file.is_file():
            row = self.row(columns["_id"])
            for key, value in row.columns():
                if key not in columns.keys():
                    columns[key] = value
        else:
            for column, options in metadata["schema"].items():
                if column not in columns.keys():
                    columns[column] = None

        has_encrypted_columns = False
        if metadata["schema"]:
            from .schema import Schema
            schema = Schema()
            for column, options in metadata["schema"].items():
                schema.add(column, options["type"], **options)

            has_encrypted_columns = schema.has_encrypted_columns()
            for key, value in columns.items():
                if not schema.valid(key, value):
                    raise ValueError(f"Invalid data type for column: {key}")

        del columns["_id"]
        columns = dict(sorted(columns.items()))

        import json
        data = json.dumps(list(columns.keys())) + "\n"
        if has_encrypted_columns:
            if not self._database.kek():
                raise ValueError("KEK required to encrypt columns")

            from .crypto import Crypto
            dek = Crypto.decrypt(self.dek(), self._database.kek())
            if not dek:
                raise ValueError("Wrong KEK provided")

            for key, value in columns.items():
                options = schema.get(key)
                if options and options["encrypted"]:
                    value = Crypto.encrypt(value, dek)

                data += json.dumps(value) + "\n"
        else:
            for key, value in columns.items():
                data += json.dumps(value) + "\n"

        from .file import File
        file = File(file)
        written = file.write(data)
        if written:
            from .cache import Cache
            cache = Cache(self)
            cache.flush()

        return written

    def insert_many(self, many_columns: list) -> list:
        """Insert rows to the table.

        Args:
            many_columms: The rows columns to insert.

        Returns:
            True for each successful insertion, and False for failed insertion.
        """
        status = []
        for columns in many_columns:
            status.append(self.insert(columns))

        return status

    def row(self, id: str) -> "Row":
        """Returns instance of Row object.

        Args:
            id: The row identifier.
        """
        from .row import Row
        return Row(id, self)

    def rows(self, exclude: list = []) -> "Generator":
        """Yields instances of Row object.

        Args:
            exclude: The rows to exclude.
        """
        for row in self.folder().iterdir():
            if row.name.endswith(".ndjson"):
                row = row.name.replace(".ndjson", "")
                if row not in exclude:
                    yield self.row(row)

    def query(self) -> "Query":
        """Returns an instance of the Query object for the table."""
        from .query import Query
        query = Query(self._database)
        query.use_table(self._name)
        return query

    def find(self, criteria: dict) -> list:
        """Returns matching rows from the table.

        Args:
            criteria: The condition to match.
        """
        query = self.query()
        for key, value in criteria.items():
            query.where(key, "=", value)

        return query.fetch()

    def find_one(self, criteria: dict) -> "Any":
        """Returns single matching row from the table.

        Args:
            criteria: The condition to match.
        """
        query = self.query()
        for key, value in criteria.items():
            query.where(key, "=", value)

        query.limit(1)
        query.no_cache()
        data = query.fetch()
        if data:
            return data[0]

        return None
