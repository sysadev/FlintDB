class File:
    """Manages file operations, including atomic writes.

    Attributes:
        _file (Path): The Path object instance for the given file.
    """
    def __init__(self, file: str) -> None:
        """Initializes the File object.

        Args:
            file: The file to work with.
        """
        from pathlib import Path
        self._file = Path(file)

    def read(self) -> str:
        """Returns the content of the file."""
        return self._file.read_text(encoding="utf-8")

    def read_json(self) -> "Any":
        """Returns the data from the working file.

        Raises:
            ValueError: If working file contains invalid JSON.
        """
        import json
        try:
            with self._file.open(mode="r", encoding="utf-8") as f:
                content = json.load(f)
            return content
        except json.JSONDecodeError:
            raise ValueError("File contains invalid JSON")

    def read_pickle(self) -> "Any":
        """Returns the data from the working file."""
        import pickle
        with self._file.open(mode="rb") as f:
            content = pickle.load(f)

        return content

    def readline(self, index: int, json_decode: bool = False) -> str:
        """Returns content from a specific line from the working file.

        Args:
            index: The index of line to retrieve.
            json_decode: To take the content as JSON and decode it.

        Raises:
            ValueError: If line contains invalid JSON.
        """
        import json
        try:
            content = ""
            with self._file.open(mode="r", encoding="utf-8") as f:
                for i, line in enumerate(f):
                    if i == index:
                        content = line.strip()
                        if json_decode:
                            content = json.loads(content)
                        break
            return content
        except json.JSONDecodeError:
            raise ValueError("Line contains invalid JSON")

    def readlines(self, json_decode: bool = False) -> list:
        """Returns list of contents of all lines from the working file.

        Args:
            json_decode: To take the contents of lines as JSON and decode it.

        Raises:
            ValueError: If line contains invalid JSON.
        """
        import json
        try:
            lines = []
            with self._file.open(mode="r", encoding="utf-8") as f:
                for i, line in enumerate(f):
                    line = line.strip()
                    if json_decode:
                        line = json.loads(line)
                    lines.append(line)
            return lines
        except json.JSONDecodeError:
            raise ValueError("File contains invalid JSON")

    def write(self, content: str) -> bool:
        """Writes text content to working file atomically.

        Args:
            content: The text content to write.

        Returns:
            True if successfully, False otherwise.
        """
        try:
            import os
            from .crypto import Crypto
            random_id = Crypto.random_id()
            file = self._file.with_suffix(".wal." + random_id)
            with file.open(mode="w", encoding="utf-8") as f:
                f.write(content)
                f.flush()
                os.fsync(f.fileno())

            file.replace(self._file)
            dir_f = os.open(self._file.parent, os.O_RDONLY)
            try:
                os.fsync(dir_f)
            finally:
                os.close(dir_f)

            return True
        except OSError:
            try:
                file.unlink()
            finally:
                return False

    def write_json(self, content: dict) -> bool:
        """Encodes data to JSON and writes to working file atomically.

        Args:
            content: The data to write.

        Returns:
            True if successfully, False otherwise.
        """
        try:
            import json, os
            from .crypto import Crypto
            random_id = Crypto.random_id()
            file = self._file.with_suffix(".wal." + random_id)
            with file.open(mode="w", encoding="utf-8") as f:
                json.dump(content, f)
                f.flush()
                os.fsync(f.fileno())

            file.replace(self._file)
            dir_f = os.open(self._file.parent, os.O_RDONLY)
            try:
                os.fsync(dir_f)
            finally:
                os.close(dir_f)

            return True
        except OSError as e:
            try:
                file.unlink()
            finally:
                return False

    def write_pickle(self, content: "Any") -> bool:
        """Encodes data with pickle and writes to working file atomically.

        Args:
            content: The data to write.

        Returns:
            True if successfully, False otherwise.
        """
        try:
            import pickle, os
            from .crypto import Crypto
            random_id = Crypto.random_id()
            file = self._file.with_suffix(".wal." + random_id)
            with file.open(mode="wb") as f:
                pickle.dump(content, f)
                f.flush()
                os.fsync(f.fileno())

            file.replace(self._file)
            dir_f = os.open(self._file.parent, os.O_RDONLY)
            try:
                os.fsync(dir_f)
            finally:
                os.close(dir_f)

            return True
        except OSError as e:
            try:
                file.unlink()
            finally:
                return False
