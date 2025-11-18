class Query:
    """Manages data querying.

    Attributes:
        _query (dict): The query content.
        _cache (bool): To allow caching query result.
        _ready (bool): To detect whether query content has been normalized.
        _table (Table): The table to query data from.
        _database (Database): The parent database of table.
        _limit (list): To slice query result.
    """
    def __init__(self, database: "Database") -> None:
        """Initializes the Query object.

        Args:
            database: The parent database instance.
        """
        self._query = {}
        self._cache = True
        self._ready = False
        self._table = None
        self._database = database
        self._limit = [0, None]

    def use_table(self, table_name: str) -> "Self":
        """Sets the table to query data from.

        Args:
            table_name: The table to query from.
        """
        self._table = self._database.table(table_name)
        return self

    def join(self, table_name: str, on: list, prefix: str | None = None) -> "Self":
        """Performs left outer join.

        Args:
            table_name: The name of the table to join.
            on: The join clause.
            prefix: The prefix for right row columns.
        """
        if "join" not in self._query:
            self._query["join"] = {}

        prefix = table_name + "." if prefix is None else prefix
        self._query["join"][table_name] = [on, prefix]
        return self

    def map(self, callback: "Callable") -> "Self":
        """Maps callback to rows.

        Args:
            callback: The callback to pass rows into.
        """
        if "map" not in self._query:
            self._query["map"] = []

        self._query["map"].append([callback, callback.__doc__])
        return self

    def where(self, column_name: str, operator: str, value: "Any") -> "Self":
        """Conditional filtering.

        Args:
            column_name: The column name to filter.
            operator: The operator sign.
            value: The value to compare.
        """
        if "where" not in self._query:
            self._query["where"] = {}

        self._query["where"][column_name] = [operator, value]
        return self

    def select(self, column_name: str, new_name: str) -> "Self":
        """Renames column.

        Args:
            column_name: The column's original name.
            new_name: The target name.
        """
        if "select" not in self._query:
            self._query["select"] = {}

        self._query["select"][column_name] = new_name
        return self

    def distinct(self, column_name: str) -> "Self":
        """Retrieves distinct, non repeated values.

        Args:
            column_name: The column to filter.
        """
        if "distinct" not in self._query:
            self._query["distinct"] = []

        self._query["distinct"].append(column_name)
        return self

    def sort(self, column_name: str, order: str = "ASC") -> "Self":
        """Sorts rows base on column's value.

        Args:
            column_name: The column to sort.
            order: The order in which to sort.

        Raises:
            ValueError: If order is not ASC or DESC.
        """
        order = order.upper()
        if order not in ("ASC", "DESC"):
            raise ValueError("Only \"ASC\" and \"DESC\" are valid orders")

        if "sort" not in self._query:
            self._query["sort"] = []

        order = True if order == "ASC" else False
        self._query["sort"].append([column_name, order])
        return self

    def filter(self, callback: "Callable") -> "Self":
        """Conditional filtering by mapping callback.

        Args:
            callback: The callback to pass rows into.
        """
        if "filter" not in self._query:
            self._query["filter"] = []

        self._query["filter"].append([callback, callback.__doc__])
        return self

    def limit(self, max: int, offset: int = 0) -> "Self":
        """Restrict the number of rows returned.

        Args:
            max: The maximum number of rows to retrieve.
            offset: The starting point for retrieving rows.

        Raises:
            ValueError: If max is less than 1.
        """
        if max < 1:
            raise ValueError("Maximum limit must be greater than zero")

        self._limit = [offset, max]
        return self

    def no_cache(self) -> "Self":
        """Disables query result caching."""
        self._cache = False
        return self

    def fetch(self) -> list:
        """Returns query result."""
        self.normalize()
        if self._cache:
            from .cache import Cache
            cache = Cache(*self.identifier())
            if cache.valid():
                limit_start = self._limit[0]
                limit_stop = self._limit[1]
                return cache.get()[limit_start:limit_stop]

        data = []
        for row in self._table.rows():
            if self._query["join"]:
                join_object = Join(row)
                for table_name, options in self._query["join"]:
                    join_object.table(self._database.table(table_name))
                    join_object.options(options[0])
                    join_row = join_object.row()
                    if join_row:
                        for key, value in join_row.columns():
                            key = options[1] + key
                            row[key] = value

            if self._query["map"]:
                for map in self._query["map"]:
                    map[0](row)

            if self._query["where"]:
                where_object = Where(row)
                for column, criteria in self._query["where"]:
                    where_object.criteria(column, *criteria)
                    if where_object.match():
                        data.append(row)
            else:
                data.append(row)

            if row not in data:
                continue

            for column_name, new_name in self._query["select"]:
                row.rename_column(column_name, new_name)

        for column in self._query["distinct"]:
            values = set(())
            data[:] = [
                row for row in data
                if row[column] not in values
                and not values.add(row[column])
            ]

        for sort in self._query["sort"]:
            try:
                data.sort(key=lambda row: row[sort[0]], reverse=sort[1])
            except TypeError:
                pass

        for _filter in self._query["filter"]:
            data = list(filter(_filter[0], data))

        if self._cache and data:
            cache.put(data)

        limit_start = self._limit[0]
        limit_stop = self._limit[1]
        return data[limit_start:limit_stop]

    def normalize(self) -> None:
        """Normalizes query content.

        Raises:
            ValueError: If table not provided.
        """
        if self._table is None:
            raise ValueError("Table must be specified")

        if self._ready:
            return

        if "join" not in self._query:
            self._query["join"] = {}
        self._query["join"] = sorted(self._query["join"].items())

        if "map" not in self._query:
            self._query["map"] = []
        self._query["map"].sort()

        if "where" not in self._query:
            self._query["where"] = {}
        self._query["where"] = sorted(self._query["where"].items())

        if "select" not in self._query:
            self._query["select"] = {}
        self._query["select"] = sorted(self._query["select"].items())

        if "distinct" not in self._query:
            self._query["distinct"] = []
        self._query["distinct"].sort()

        if "sort" not in self._query:
            self._query["sort"] = []
        self._query["sort"].sort()

        if "filter" not in self._query:
            self._query["filter"] = []
        self._query["filter"].sort()

        self._query = dict(sorted(self._query.items()))
        self._ready = True

    def identifier(self) -> list:
        """Returns table and query content."""
        self.normalize()
        table = self._table
        query = self._query
        return [table, query]

    def clear(self) -> "Self":
        """Resets query instance."""
        self._query = {}
        self._cache = True
        self._ready = False
        self._limit = [0, None]
        return self


class Join:
    """Manages table joins.

    Attributes:
        _table (Table): The table to join.
        _options (list): The join clause.
        _where (Where): The instance of Where object.
    """
    def __init__(self, row: "Row") -> None:
        """Initializes Join object.

        Args:
            row: The row from left table.
        """
        self._table = None
        self._options = None
        self._where = Where(row)

    def table(self, table: "Table") -> None:
        """Sets the table to join.

        Args:
            table: The table to join.
        """
        self._table = table

    def options(self, options: list) -> None:
        """Sets the join clause.

        Args:
            options: The join clause.
        """
        self._options = options

    def filter(self, row: "Row") -> bool:
        """Filter rows.

        Args:
            row: The row from right table.

        Returns:
            True if it matches.
        """
        left_column = self._options[0]
        operator = self._options[1]
        right_column = self._options[2]
        value = row[right_column]

        self._where.criteria(left_column, operator, value)
        return self._where.match()

    def row(self) -> "Any":
        """Returns matching row or None."""
        query = self._table.query()
        query.no_cache()
        query.filter(self.filter)
        data = query.fetch()
        if not data:
            return None

        return data[0]


class Where:
    """Manages conditional filtering.

    Attributes:
        _criteria (list): The filtering criteria.
        _row (Row): The row to filter.
    """
    def __init__(self, row: "Row") -> None:
        """Initializes Where object.

        Args:
            row: The row to filter.
        """
        self._criteria = None
        self._row = row

    def criteria(self, column_name: str, operator: str, value: "Any") -> None:
        """Sets filtering criteria.

        Args:
            column_name: The column name to filter.
            operator: The operator sign.
            value: The value to compare.
        """
        self._criteria = [column_name, operator, value]

    def match(self) -> bool:
        """Returns whether conditions are met."""
        if not self._criteria:
            return False

        try:
            value = self._criteria[2]
            row_value = self._row[self._criteria[0]]
            match self._criteria[1]:
                case "=" | "eq" | "is":
                    return row_value == value
                case "!=" | "neq" | "is not":
                    return row_value != value
                case ">" | "gt":
                    return row_value > value
                case ">=" | "gte":
                    return row_value >= value
                case "<" | "lt":
                    return row_value < value
                case "<=" | "lte":
                    return row_value <= value
                case "in" | "is in":
                    return row_value in value
                case "not in":
                    return row_value not in value
                case "between":
                    return row_value >= value[0] and row_value <= value[1]
                case "not between":
                    return row_value <= value[0] and row_value >= value[1]
                case "like":
                    import re
                    pattern = re.escape(str(value))
                    if "%" not in pattern and "_" not in pattern:
                        return row_value == value

                    text = str(row_value)
                    pattern = re.sub(r"\%", ".*", pattern)
                    pattern = re.sub(r"\_", ".", pattern)
                    return re.search(pattern, text) is not None
                case "not like":
                    import re
                    pattern = re.escape(str(value))
                    if "%" not in pattern and "_" not in pattern:
                        return row_value != value

                    text = str(row_value)
                    pattern = re.sub(r"\%", ".*", pattern)
                    pattern = re.sub(r"\_", ".", pattern)
                    return re.search(pattern, text) is None
                case _:
                    return False
        except (TypeError, IndexError):
            return False
