class Schema:
    """Manages Schema definition and enforcing.

    Attributes:
        _schema (dict): The defined schema.
        _has_encrypted_columns (bool): To check for encrypted columns presence.
    """
    def __init__(self) -> None:
        """Initializes Schema object."""
        self._schema = {}
        self._has_encrypted_columns = False

    def __len__(self) -> int:
        """Returns the count of defined schema."""
        return len(self._schema)

    def add(self, column_name: str, column_type: str, **kwargs) -> "Self":
        """Defines new column schema.

        Args:
            column_name: The column name.
            column_type: The column data type.
            **kwargs: Arbitrary keyword argument.
                The arguments can include:
                    encrypted (bool): To encrypt column value.
                    required (bool): To make column not nullable.
                    enum_values (list): The enum type values.

        Raises:
            ValueError: If column type in not supported. Or if invalid enum values given.
            TypeError: If enum values are not list

        Returns:
            Schema instance.
        """
        if column_type not in Schema.valid_types().keys():
            raise ValueError(f"Unsupported type for column \"{column_name}\"")

        if encrypted := bool(kwargs.get("encrypted")):
            self._has_encrypted_columns = True

        self._schema[column_name] = {
            "type": column_type,
            "encrypted": encrypted,
            "required": bool(kwargs.get("required"))
        }

        if "enum" == column_type:
            enum_values = kwargs.get("enum_values")
            if not isinstance(enum_values, list):
                raise TypeError("ENUM values must be of type list")
            elif not enum_values:
                raise ValueError("ENUM values cannot be empty")

            enum_type = {type(value) for value in enum_values}
            if len(enum_type) > 1:
                raise ValueError("ENUM values must be of the same type")

            self._schema[column_name]["enum_values"] = list(set(enum_values))

        return self

    def get(self, column_name: str) -> dict | None:
        """Retrieves column schema.

        Args:
            column_name: The column name.

        Returns:
            Schema definition for column. Or None if column is not defined.
        """
        if column_name not in self._schema:
            return None

        return self._schema[column_name]

    def valid(self, column_name: str, value: "Any") -> bool:
        """Returns whether value is valid data for column.

        Args:
            column_name: The column name.
            value: The value.
        """
        data = self.get(column_name)
        if not data:
            return True
        elif not data["required"] and value is None:
            return True
        elif "enum" == data["type"] and value not in data["enum_values"]:
            return False

        valid = False
        valid_types = Schema.valid_types().get(data["type"])
        for types in valid_types:
            if isinstance(value, types):
                valid = True

        return valid

    def remove(self, column_name: str) -> None:
        """Removes column from schema definition.

        Args:
            column_name: The column name.
        """
        if column_name in self._schema:
            del self._schema[column_name]

    def sorted_schema(self) -> dict:
        """Returns sorted schema definition."""
        return dict(sorted(self._schema.items()))

    def has_encrypted_columns(self) -> bool:
        """Returns whether schema has encrypted column(s)"""
        return self._has_encrypted_columns

    @staticmethod
    def valid_types() -> dict:
        """Returns supported columns data types."""
        return {
            "@list": (list,),
            "@bool": (bool,),
            "@enum": (list, bool, float, int, dict, str),
            "@float": (float,),
            "@integer": (int,),
            "@numeric": (str, int, float),
            "@object": (dict,),
            "@text": (str,)
        }
