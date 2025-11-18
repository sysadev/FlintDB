class Backup:
    """Utility class for handling backup and recovery of database."""
    @staticmethod
    def dump(database: "Database", file: str) -> None:
        """Backup database to a zip file.

        Args:
            database: The instance of Database to backup.
            file: The filesystem path to the zip file.
        """
        from zipfile import ZipFile, ZIP_DEFLATED
        with ZipFile(file, mode="w", compression=ZIP_DEFLATED, compresslevel=9) as zip:
            zip.mkdir(database.name())
            zip.write(database.folder() / ".metadata", arcname=database.name() + "/.metadata")
            for table in database.tables():
                zip.mkdir(database.name() + "/" + table.name())
                zip.write(table.folder() / ".metadata", arcname=database.name() + "/" + table.name() + "/.metadata")
                for row in table.rows():
                    zip.write(row.file(), arcname=database.name() + "/" + table.name() + "/" + row.id() + ".ndjson")

    @staticmethod
    def load(file: str, storage: str) -> None:
        """Recover database backup from zip file.

        Args:
            file: The filesystem path to the zip file.
            storage: The directory path where the database folder should reside.
        """
        from zipfile import ZipFile
        with ZipFile(file, mode="r") as zip:
            zip.extractall(storage)
