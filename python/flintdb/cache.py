class Cache:
    """Manages caching of query results.

    Attributes:
        _table (Table): The instance of table for the data to cache.
        _query (dict): The query content.
        _expiration (int, float, None): The date timestamp for when cache will expire.
    """
    def __init__(self, table: "Table", query: dict | None = None, expiration: int | float | None = None) -> None:
        """Initializes the Cache object.

        Args:
            table: The instance of table for the data to cache.
            query: The query content.
            expiration: Expiration timestamp.

        Raises:
            OSError: If failed to create necessary folders.
        """
        self._table = table
        self._query = query
        self._expiration = expiration

        folder = table.folder().parent
        self._folder = folder / ".cache" / table.name()
        if not self._folder.is_dir():
            try:
                self._folder.mkdir(parents=True)
            except OSError as e:
                raise OSError(f"Failed to create cache directory: {e}")

    def file(self) -> "Path":
        """Returns instance of Path for the cache file."""
        from .crypto import Crypto
        hash = Crypto.hash(self._query)
        return self._folder / hash

    def valid(self) -> bool:
        """Returns whether cache is valid."""
        file = self.file()
        if not file.is_file():
            return False
        elif self._expiration is None:
            return True
        elif file.stat().st_birthtime > self._expiration:
            file.unlink()
            return False

        return True

    def put(self, data: list) -> bool:
        """Writes cache data to the cache file.

        Args:
            data: The data to cache.

        Returns:
            True if successfully, False otherwise.
        """
        from .file import File
        file = File(self.file())
        return file.write_pickle(data)

    def get(self) -> list:
        """Returns cached data."""
        from .file import File
        file = File(self.file())
        return file.read_pickle()

    def delete(self) -> bool:
        """Deletes cache file."""
        return self.file().unlink()

    def flush(self) -> bool:
        """Deletes all cache files related to the table."""
        try:
            import shutil
            shutil.rmtree(self._folder)
            return True
        except OSError:
            return False
