class Row:
    """Represents a single record (document) within a table.

    Attributes:
        _id (str): The row identifier.
        _table (Table): The parent Table instance.
        _schema (dict): The row's columns name.
        _readonly (bool): To disallow updating row.
        _custom (dict): In-memory columns.
    """
    def __init__(self, id: str, table: "Table") -> None:
        """Initializes the Row object.

        Args:
            id: The row identifier.
            table: The instance of the parent table.
        """
        self._id = id
        self._table = table
        self._schema = None
        self._readonly = False
        self._custom = {}

    def __setitem__(self, key: str, value: "Any") -> None:
        """Sets a column to in-memory columns.

        Args:
            key: The column name.
            value: The column value.
        """
        self._custom[key] = value

    def __getitem__(self, key: str) -> "Any":
        """Retrieves column value.

        Args:
            key: The column name.
        """
        return self.column(key)

    def __delitem__(self, key: str) -> None:
        """Removes a column from in-memory columns.

        Args:
            key: The column name.
        """
        del self._custom[key]

    def __len__(self) -> int:
        """Returns the number of columns in the row."""
        return len(self.schema()) + len(self._custom.keys())

    def __iter__(self) -> iter:
        """Returns row columns."""
        return self.columns()

    def __contains__(self, key: str) -> bool:
        """Returns whether column exists.

        Args:
            key: The column name.
        """
        return key in self.columns()

    def id(self) -> str:
        """Returns row identifier."""
        return self._id

    def file(self) -> "Path":
        """Returns Path instance of row file."""
        return self._table.folder() / f"{self._id}.ndjson"

    def metadata(self) -> dict:
        """Returns row metadata."""
        stat = self.file().stat()
        return {
            "id": self._id,
            "table": self._table,
            "readonly": self._readonly,
            "created": stat.st_birthtime,
            "modified": stat.st_mtime,
            "size": stat.st_size
        }

    def delete(self) -> bool:
        """Deletes row file."""
        if self._readonly:
            return False

        try:
            self.file().unlink()
            from .cache import Cache
            cache = Cache(self._table)
            cache.flush()
            return True
        except OSError:
            return False

    def update(self, columns: dict) -> bool:
        """Updates row columns.

        Args:
            columns: The columns to update.

        Returns:
            True if successfully, False otherwise.
        """
        if self._readonly:
            return False

        columns["_id"] = self._id
        return self._table.insert(columns)

    def readonly(self) -> None:
        """Disallow updating row columns."""
        self._readonly = True

    def schema(self) -> list:
        """Returns list of row columns name."""
        if not self._schema:
            self._schema = self.index(0)

        return self._schema

    def column(self, column_name: str) -> "Any":
        """Returns column value.

        Args:
            column_name: The column name.
        """
        if "_id" == column_name:
            return self._id

        schema = self.schema()
        if column_name not in schema:
            return self._custom.get(column_name)

        index = schema.index(column_name)
        data = self.index(index + 1)

        from .crypto import Crypto
        if self._table.schema():
            column = self._table.schema().get(column_name)
            if column and column.get("encrypted"):
                if not self._table.database().kek():
                    raise ValueError("KEK must be provided to decrypt columns")

                dek = Crypto.decrypt(self._table.dek(), self._table.database().kek())
                if not dek:
                    raise ValueError("Invalid KEK provided")

                data = Crypto.decrypt(data, dek)

        return data

    def columns(self) -> "Generator":
        """Yields row columns."""
        from .file import File
        file = File(self.file())
        values = file.readlines(json_decode=True)
        keys = self.schema()
        del values[0]

        from .schema import Schema
        schema = Schema()
        for column, options in self._table.schema().items():
            schema.add(column, options["type"], **options)

        from .crypto import Crypto
        if schema.has_encrypted_columns():
            if not self._table.database().kek():
                raise ValueError("KEK must be provided to decrypt columns")

            dek = Crypto.decrypt(self._table.dek(), self._table.database().kek())
            if not dek:
                raise ValueError("Invalid KEK provided")

        while keys and values:
            key = keys[0]
            value = values[0]
            if schema.get(key) and schema.get(key)["encrypted"]:
                value = Crypto.decrypt(value, dek)

            yield (key, value)
            del values[0]
            del keys[0]

        yield from self._custom.items()
        yield ("_id", self._id)

    def index(self, index: int) -> "Any":
        """Returns content of a line from row's file.

        Args:
            index: The line index number.
        """
        from .file import File
        file = File(self.file())
        return file.readline(index, json_decode=True)

    def rename_column(self, column_name: str, new_name: str) -> None:
        """Temporarily renames column.

        Args:
            column_name: The original column name.
            new_name: The target new name.
        """
        if column_name in self.schema():
            index = self._schema.index(column_name)
            self._schema[index] = new_name
