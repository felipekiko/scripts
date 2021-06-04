import json
from mysql.connector import connect, Error

# Set connections to compare
db_connections = [
    {
        "dbkeyn": "NAME OF YOUR FIRST DATABASE",
        "dbhost": "YOUR FIRST HOST",
        "dbuser": "YOUR FIRST USER",
        "dbpass": "YOUR FIRST PASSWORD",
        "dbname": "YOUR FIRST DATABASE NAME"
    },
    {
        "dbkeyn": "NAME OF YOUR SECOND DATABASE",
        "dbhost": "YOUR SECOND HOST",
        "dbuser": "YOUR SECOND USER",
        "dbpass": "YOUR SECOND PASSWORD",
        "dbname": "YOUR SECOND DATABASE NAME"
    }
]

# Get all results
results = {}
for db_connection in db_connections:
    try:
        with connect(
            host        = db_connection['dbhost'],
            user        = db_connection['dbuser'],
            password    = db_connection['dbpass'],
            database    = db_connection['dbname']
        ) as connection:
            with connection.cursor() as cursor:
                # Get all tables
                cursor.execute(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{db_connection['dbname']}'")
                result_tables = cursor.fetchall()

                # Make count of table rows
                for row in result_tables:
                    cursor.execute(f"SELECT COUNT(1) FROM {row[0]}")
                    result_count = cursor.fetchall()

                    if row[0] in results:
                        results[row[0]].update({
                            db_connection['dbkeyn']: result_count[0][0]
                        })
                    else:
                        results.update({
                            row[0]: {
                                db_connection['dbkeyn']: result_count[0][0]
                            }
                        })
    except Error as e:
        print(e)

# Header
print(f",{db_connections[0]['dbkeyn']},{db_connections[1]['dbkeyn']}")

# Compare databases
for result in results:
    row_first = results[result][db_connections[0]['dbkeyn']] if db_connections[0]['dbkeyn'] in results[result] else '-'
    row_second = results[result][db_connections[1]['dbkeyn']] if db_connections[1]['dbkeyn'] in results[result] else '-'
    
    print(f"{result},{row_first},{row_second}")
