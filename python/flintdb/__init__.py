"""
FlintDB is a lightweight, high-performance flat-file NoSQL database engine.

Usage example:

>> from flintdb import Database
>> db = Database("dbname", "./data_dir")
>> db.create_table("tblname")
>>
>> tbl = db.table("tblname")
>> tbl.insert({
>>     "column_1": "column value",
>>     "column_2": 1024,
>>     "column_3": 3.142857,
>>     "column_4": True
>> })
>>
>> col = tbl.find_one({"column_6": True})
>> dict(col)
{'column_1': 'column value', 'column_2': 1024, 'column_3': 3.142857, 'column_4': True, '_id': 'a846f56709cd29c1'}

"""
from .backup import Backup
from .crypto import Crypto
from .database import Database
from .schema import Schema
from .version import __version__

__all__ = ["Backup", "Crypto", "Database", "Schema"]
