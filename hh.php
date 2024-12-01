<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;  // This is the correct import
use Illuminate\Support\Facades\Log;

class DatabaseController extends Controller
{
    // Fetch and display all databases
    public function index()
    {
        // Fetch database names from General_BD_TABLES
        $databases = DB::table('General_BD_TABLES')->pluck('db_name', 'db_id');
        
        // Fetch the query history
        $queries = DB::table('General_QUERY_table')
                     ->orderBy('timestamp_insert', 'desc')
                     ->get();

        // Return the view and pass both the databases and queries
        return view('TP.index', compact('databases', 'queries'));
    }

    // Fetch and display tables based on the clicked database
    public function getTables($db_id)
    {
        try {
            $tables = DB::table('GENERAl_TABLE_TABLES')
                ->where('db_id', $db_id)
                ->orderBy('timestamp_insert', 'desc')
                ->pluck('table_name');

            if ($tables->isEmpty()) {
                return response()->json(['message' => 'No tables found for this database.'], 404);
            }

            return response()->json(['tables' => $tables]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch tables. ' . $e->getMessage()], 500);
        }
    }

    public function runQuery(Request $request)
    {
        $userQuery = trim($request->input('sql_query'));
        $response = ['success' => false, 'message' => ''];
    
        try {
            // Detect the type of the query
            $queryType = $this->getQueryType($userQuery);
            
            // Handle 'USE' queries
            if ($queryType == 'USE') {
                if (preg_match('/USE\s+([a-zA-Z0-9_]+)/i', $userQuery, $matches)) {
                    $dbName = $matches[1];
                    session(['selected_db' => $dbName]);
                    return response()->json([
                        'success' => true,
                        'selected_db' => $dbName,
                    ]);
                }
            }
            // Handle 'SHOW TABLES' queries
            elseif ($queryType == 'SHOW_TABLES') {
                $dbName = session('selected_db');
                
                if ($dbName) {
                    $dbRecord = DB::table('general_bd_tables')
                                  ->where('db_name', $dbName)
                                  ->first();
    
                    if ($dbRecord) {
                        $tablesResponse = $this->getTables($dbRecord->id_bd);
    
                        if ($tablesResponse->getStatusCode() == 200) {
                            $tables = json_decode($tablesResponse->getContent(), true)['tables'];
                            return response()->json([
                                'success' => true,
                                'db_name' => $dbName,
                                'db_id' => $dbRecord->id_bd,
                                'tables' => $tables
                            ]);
                        }
                        return $tablesResponse;
                    } else {
                        return response()->json(['success' => false, 'error' => 'Database not found.']);
                    }
                } else {
                    return response()->json(['success' => false, 'error' => 'No database selected.']);
                }
            }
            // Handle 'ALTER TABLE MODIFY NAME' queries
            elseif ($queryType == 'MODIFY_TABLE') {
                $dbName = session('selected_db');
                if (!$dbName) {
                    throw new \Exception('No database selected.');
                }
    
                $alterResult = $this->modifyTable($userQuery);
                DB::update($alterResult['sql'], $alterResult['bindings']);
    
                return response()->json([
                    'success' => true,
                    'message' => 'Table modified successfully.'
                ]);

            }


          // Handle 'DELETE' queries
        elseif ($queryType == 'DELETE_VALUES') {
            $deleteResult = $this->deleteDataFromTable($userQuery);

            // Start a database transaction
            DB::beginTransaction();

            try {
                // Execute each SQL query individually
                foreach ($deleteResult['sql'] as $index => $sql) {
                    DB::delete($sql, $deleteResult['bindings'][$index]);
                }

                // Commit the transaction
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Data deleted successfully.'
                ]);
            } catch (\Exception $e) {
                // Rollback the transaction if any query fails
                DB::rollBack();
                throw new Exception('Transaction failed: ' . $e->getMessage());
            }
        }elseif ($queryType == 'DROP_DATABASE') {
            $dropResult = $this->dropDatabase($userQuery);

            // Start a database transaction
            DB::beginTransaction();

            try {
                // Execute each SQL query individually
                foreach ($dropResult['sql'] as $index => $sql) {
                    DB::delete($sql, $dropResult['bindings'][$index]);
                }

                // Commit the transaction
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Database dropped successfully.'
                ]);
            } catch (\Exception $e) {
                // Rollback the transaction if any query fails
                DB::rollBack();
                throw new Exception('Transaction failed: ' . $e->getMessage());
            }
        }
       // Handle 'CREATE TABLE' queries
       elseif ($queryType == 'CREATE_TABLE') {
        $createResult = $this->createTable($userQuery);

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Execute each SQL query individually
            foreach ($createResult['sql'] as $index => $sql) {
                DB::statement($sql, $createResult['bindings'][$index]);
            }

            // Commit the transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Table created successfully.'
            ]);
        } catch (\Exception $e) {
            // Rollback the transaction if any query fails
            DB::rollBack();
            throw new Exception('Transaction failed: ' . $e->getMessage());
        }
    }



    // Handle 'DROP TABLE' queries
    elseif ($queryType == 'DROP_TABLE') {
        $dropResult = $this->dropTable($userQuery);

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Execute each SQL query individually
            foreach ($dropResult['sql'] as $index => $sql) {
                DB::statement($sql, $dropResult['bindings'][$index]);
            }

            // Commit the transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Table dropped successfully.'
            ]);
        } catch (\Exception $e) {
            // Rollback the transaction if any query fails
            DB::rollBack();
            throw new Exception('Transaction failed: ' . $e->getMessage());
        }
    }

      // Handle 'INSERT' queries
      elseif ($queryType == 'INSERT_VALUES') {
        $insertResult = $this->insertDataIntoTable($userQuery);

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Execute each SQL query individually
            foreach ($insertResult['sql'] as $index => $sql) {
                DB::statement($sql, $insertResult['bindings'][$index]);
            }

            // Commit the transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data inserted successfully.'
            ]);
        } catch (\Exception $e) {
            // Rollback the transaction if any query fails
            DB::rollBack();
            throw new Exception('Transaction failed: ' . $e->getMessage());
        }
    }
    // Handle 'UPDATE' queries
    elseif ($queryType == 'UPDATE_VALUES') {
        $updateResult = $this->modifyValue($userQuery);

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Execute each SQL query individually
            foreach ($updateResult['sql'] as $index => $sql) {
                DB::statement($sql, $updateResult['bindings'][$index]);
            }

            // Commit the transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.'
            ]);
        } catch (\Exception $e) {
            // Rollback the transaction if any query fails
            DB::rollBack();
            throw new Exception('Transaction failed: ' . $e->getMessage());
        }
    }



            


            // Handle other query types: INSERT, SELECT, DELETE, etc.
            else {
                $internalQuery = $this->generateInternalQuery($queryType, $userQuery);
                $result = DB::statement($internalQuery['sql'], $internalQuery['bindings']);
    
                // Refresh databases or fetch new data depending on query type
                $databases = DB::select('SELECT db_name FROM general_bd_tables');
                return response()->json([
                    'success' => true,
                    'message' => 'Query executed successfully.',
                    'databases' => $databases,
                    'result' => $result ?? null
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
    




    // Fonction pour détecter le type de la requête
    private function getQueryType($query)
{
    if (preg_match('/^\s*CREATE\s+DATABASE/i', $query)) {
        return 'CREATE_DATABASE';
    }elseif (preg_match('/^\s*USE/i', $query)) {
        return 'USE';
    } elseif (preg_match('/^\s*ALTER\s+DATABASE/i', $query)) {
        return 'MODIFY_DATABASE';
    } elseif (preg_match('/^\s*DROP\s+DATABASE/i', $query)) {
        return 'DROP_DATABASE';
    } elseif (preg_match('/^\s*CREATE\s+TABLE/i', $query)) {
        return 'CREATE_TABLE';
    } elseif (preg_match('/^\s*SHOW\s+TABLES/i', $query)) {
        return 'SHOW_TABLES';
    } elseif (preg_match('/^\s*DROP\s+TABLE/i', $query)) {
        return 'DROP_TABLE';
    } elseif (preg_match('/^\s*ALTER\s+TABLE/i', $query)) {
        return 'MODIFY_TABLE';
    } elseif (preg_match('/^\s*INSERT\s+INTO/i', $query)) {
        return 'INSERT_VALUES';
    } elseif (preg_match('/^\s*DELETE\s+FROM/i', $query)) {
        return 'DELETE_VALUES';
    } elseif (preg_match('/^\s*SELECT\s+/i', $query)) {
        return 'SELECT_VALUES';
    } elseif (preg_match('/^\s*UPDATE\s+([a-zA-Z0-9_]+)\s+SET\s+([^=]+)\s*=\s*([^;]+)/i', $query)) {
        return 'UPDATE_VALUES';
    } elseif (preg_match('/^\s*ALTER\s+TABLE\s+ADD\s+COLUMN/i', $query)) {
        return 'ADD_COLUMN';
    } elseif (preg_match('/^\s*ALTER\s+TABLE\s+MODIFY\s+COLUMN/i', $query)) {
        return 'MODIFY_COLUMN';
    } elseif (preg_match('/^\s*ALTER\s+TABLE\s+DROP\s+COLUMN/i', $query)) {
        return 'DROP_COLUMN';
    } elseif (preg_match('/^\s*ALTER\s+TABLE\s+ADD\s+PRIMARY\s+KEY/i', $query)) {
        return 'ADD_PRIMARY_KEY';
    } elseif (preg_match('/^\s*ALTER\s+TABLE\s+ADD\s+FOREIGN\s+KEY/i', $query)) {
        return 'ADD_FOREIGN_KEY';
    } elseif (preg_match('/^\s*ALTER\s+TABLE\s+DROP\s+PRIMARY\s+KEY/i', $query)) {
        return 'DROP_PRIMARY_KEY';
    } elseif (preg_match('/^\s*ALTER\s+TABLE\s+DROP\s+FOREIGN\s+KEY/i', $query)) {
        return 'DROP_FOREIGN_KEY';
    } elseif (preg_match('/^\s*ALTER\s+TABLE\s+MODIFY\s+PRIMARY\s+KEY/i', $query)) {
        return 'MODIFY_PRIMARY_KEY';
    } elseif (preg_match('/^\s*ALTER\s+TABLE\s+MODIFY\s+FOREIGN\s+KEY/i', $query)) {
        return 'MODIFY_FOREIGN_KEY';
    }

    throw new Exception('Unsupported query type.');
}
    // Fonction pour générer la requête interne basée sur le type de requête
    private function generateInternalQuery($type, $query)
    {
        switch ($type) {
                case 'CREATE_DATABASE':
                    return $this->createDatabase($query);
                case 'USE':
                    return $this->useDatabase($query);
                case 'MODIFY_DATABASE':
                    return $this->modifyDatabase($query);
                case 'CREATE_TABLE':
                    return $this->createTable($query);
                case 'SHOW_TABLES':
                    return $this->showTables($query);
                case 'DROP_TABLE':
                    return $this->dropTable($query);
                case 'MODIFY_TABLE':
                    return $this->modifyTable($query);
                case 'INSERT_VALUES':
                    return $this->insertDataIntoTable($query);
                case 'DELETE_VALUES':
                    return $this->deleteDataFromTable($query);
                case 'UPDATE_VALUES':
                    return $this->modifyValue($query);
                case 'DROP_DATABASE':
                    return $this->dropDatabase($query);
                case 'ADD_COLUMN':
                    return $this->addColumnToTable($query);
                case 'MODIFY_COLUMN':
                    return $this->modifyColumnInTable($query);
                case 'DROP_COLUMN':
                    return $this->dropColumnFromTable($query);
                case 'ADD_PRIMARY_KEY':
                    return $this->addPrimaryKey($query);
                case 'ADD_FOREIGN_KEY':
                    return $this->addForeignKey($query);
                case 'DROP_PRIMARY_KEY':
                    return $this->deletePrimaryKey($query);
                case 'DROP_FOREIGN_KEY':
                    return $this->deleteForeignKey($query);
                case 'SELECT_VALUES':
                    return $this->selectDataFromTable($query);
                case 'MODIFY_PRIMARY_KEY':
                    return $this->modifyPrimaryKey($query);
                case 'MODIFY_FOREIGN_KEY':
                    return $this->modifyForeignKey($query);
                default:
                    return response()->json(['success' => false, 'message' => 'Unsupported query type.'], 400);
            }
        }
    
        
        private function useDatabase($query){
        if (preg_match('/USE\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
            $dbName = $matches[1];
            return [
                'sql' => 'SELECT * from GENERAL_BD_TABLES where db_name = ?',
                'bindings' => [$dbName]
            ];
        }
        throw new Exception('Invalid CREATE DATABASE query.');
    }



    // Gérer la requête CREATE DATABASE
    private function createDatabase($query)
    {
        if (preg_match('/CREATE\s+DATABASE\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
            $dbName = $matches[1];
            return [
                'sql' => 'INSERT INTO General_BD_Tables (db_name, timestamp_insert) VALUES (?, NOW())',
                'bindings' => [$dbName]
            ];
        }
        throw new Exception('Invalid CREATE DATABASE query.');
    }


    private function modifyDatabase($query)
    {
        if (preg_match('/ALTER\s+DATABASE\s+([a-zA-Z0-9_]+)\s+MODIFY\s+NAME\s+TO\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
            $oldDbName = $matches[1];
            $newDbName = $matches[2];
            return [
                'sql' => 'UPDATE general_bd_tables SET db_name = ? WHERE db_name = ?',
                'bindings' => [$newDbName, $oldDbName]
            ];
        }
        throw new Exception('Invalid ALTER DATABASE query.');
    }


    private function modifyTable($query)
    {
        $dbName = session('selected_db');
        $dbRecord = DB::table('general_bd_tables')
        ->where('db_name', $dbName)
        ->first();
        // dd($dbRecord->id_bd);
        // Vérifier si une base de données a été sélectionnée
        if (!$dbName) {
            throw new Exception('No database selected. Use the "USE" command to select a database.');
        }
        if (preg_match('/ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+RENAME\s+TO\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
            $oldTableName = $matches[1];
            $newTableName = $matches[2];
            return [
                'sql' => 'UPDATE General_TABLE_Tables SET table_name = ? WHERE table_name = ? AND db_id = ?;',
                'bindings' => [$newTableName,$oldTableName,$dbRecord->db_id]
            ];
        }
        throw new Exception('Invalid ALTER DATABASE query.');
    }


    
            
    private function deleteDataFromTable($query)
    {
        // Extract the table name and the attribute values from the DELETE query
        if (preg_match('/DELETE\s+FROM\s+([a-zA-Z0-9_]+)\s+WHERE\s+([a-zA-Z0-9_]+)\s*=\s*([0-9]+)/i', $query, $matches)) {
            $tableName = $matches[1];
            $attributeName = $matches[2];
            $attributeValue = $matches[3];
    
            // Return the SQL queries and bindings
            return [
                'sql' => [
                    'DELETE FROM General_VALUE_Tables WHERE table_id = (SELECT table_id FROM General_TABLE_Tables WHERE table_name = ?) AND attribute_values = ?',
                    'DELETE FROM General_FKEY_Tables WHERE table_id = (SELECT table_id FROM General_TABLE_Tables WHERE table_name = ?) AND attribute_id = (SELECT attribute_id FROM General_ATTRIBUTE_Tables WHERE table_id = (SELECT table_id FROM General_TABLE_Tables WHERE table_name = ?) AND attribute_name = ?)',
                    'DELETE FROM General_ATTRIBUTE_Tables WHERE table_id = (SELECT table_id FROM General_TABLE_Tables WHERE table_name = ?) AND attribute_name = ?',
                    'DELETE FROM General_TABLE_Tables WHERE table_name = ?'
                ],
                'bindings' => [
                    [$tableName, $attributeValue],
                    [$tableName, $tableName, $attributeName],
                    [$tableName, $attributeName],
                    [$tableName]
                ]
            ];
        }
    
        throw new Exception('Invalid DELETE query.');
    }
    
            
    

    private function dropDatabase($query)
    {
        // Extract the database name from the DROP DATABASE query
        if (preg_match('/DROP\s+DATABASE\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
            $dbName = $matches[1];
    
            // Check if the database exists
            $dbExists = DB::table('General_BD_Tables')->where('db_name', $dbName)->exists();
            if (!$dbExists) {
                throw new Exception("Database '$dbName' does not exist.");
            }
    
            // Return the SQL queries and bindings
            return [
                'sql' => [
                    'DELETE FROM General_FKEY_Tables WHERE table_id IN (SELECT table_id FROM General_TABLE_Tables WHERE db_id = (SELECT db_id FROM General_BD_Tables WHERE db_name = ?))',
                    'DELETE FROM General_ATTRIBUTE_Tables WHERE table_id IN (SELECT table_id FROM General_TABLE_Tables WHERE db_id = (SELECT db_id FROM General_BD_Tables WHERE db_name = ?))',
                    'DELETE FROM General_VALUE_Tables WHERE table_id IN (SELECT table_id FROM General_TABLE_Tables WHERE db_id = (SELECT db_id FROM General_BD_Tables WHERE db_name = ?))',
                    'DELETE FROM General_TABLE_Tables WHERE db_id = (SELECT db_id FROM General_BD_Tables WHERE db_name = ?)',
                    'DELETE FROM General_BD_Tables WHERE db_name = ?'
                ],
                'bindings' => [
                    [$dbName],
                    [$dbName],
                    [$dbName],
                    [$dbName],
                    [$dbName]
                ]
            ];
        }
    
        throw new Exception('Invalid DROP DATABASE query.');
    }



    private function dropTable($query)
{
    // Extract the table name from the DROP TABLE query
    if (preg_match('/DROP\s+TABLE\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
        $tableName = $matches[1];

        // Check if the table exists
        $tableExists = DB::table('General_TABLE_Tables')->where('table_name', $tableName)->exists();
        if (!$tableExists) {
            throw new Exception("Table '$tableName' does not exist.");
        }

        // Get the table ID
        $tableRecord = DB::table('General_TABLE_Tables')->where('table_name', $tableName)->first();
        $tableId = $tableRecord->table_id;

        // Return the SQL queries and bindings
        return [
            'sql' => [
                'DELETE FROM General_FKEY_Tables WHERE table_id = ?',
                'DELETE FROM General_ATTRIBUTE_Tables WHERE table_id = ?',
                'DELETE FROM General_PKEY_Tables WHERE table_id = ?',
                'DELETE FROM General_TABLE_Tables WHERE table_id = ?'
            ],
            'bindings' => [
                [$tableId],
                [$tableId],
                [$tableId],
                [$tableId]
            ]
        ];
    }

    throw new Exception('Invalid DROP TABLE query.');
}







    private function createTable($query)
    {
        // Extract the table name and columns from the CREATE TABLE query
        if (preg_match('/CREATE\s+TABLE\s+([a-zA-Z0-9_]+)\s*\(([^)]+)\)/i', $query, $matches)) {
            $tableName = $matches[1];
            $columns = $matches[2];
    
            // Parse the columns
            $columnsArray = [];
            $columnPattern = '/([a-zA-Z0-9_]+)\s+([a-zA-Z0-9_]+)(?:\s+PRIMARY\s+KEY)?(?:\s+FOREIGN\s+KEY\s+REFERENCES\s+([a-zA-Z0-9_]+)\s*\(([a-zA-Z0-9_]+)\))?/i';
            if (preg_match_all($columnPattern, $columns, $columnMatches, PREG_SET_ORDER)) {
                foreach ($columnMatches as $columnMatch) {
                    $columnsArray[] = [
                        'column_name' => $columnMatch[1],
                        'data_type' => $columnMatch[2],
                        'is_primary_key' => strpos($columnMatch[0], 'PRIMARY KEY') !== false,
                        'is_foreign_key' => strpos($columnMatch[0], 'FOREIGN KEY') !== false,
                        'reference_table' => $columnMatch[3] ?? null,
                        'reference_column' => $columnMatch[4] ?? null,
                    ];
                }
            }
    
            // Check if the database is selected
            $dbName = session('selected_db');
            if (!$dbName) {
                throw new Exception('No database selected. Use the "USE" command to select a database.');
            }
    
            // Get the database ID
            $dbRecord = DB::table('General_BD_Tables')->where('db_name', $dbName)->first();
            if (!$dbRecord) {
                throw new Exception("Database '$dbName' does not exist.");
            }
            $dbId = $dbRecord->db_id;
    
            // Start a database transaction
            DB::beginTransaction();
    
            try {
                // Insert the table into General_TABLE_Tables
                $tableId = DB::table('General_TABLE_Tables')->insertGetId([
                    'db_id' => $dbId,
                    'table_name' => $tableName,
                    'timestamp_insert' => now()
                ]);
    
                // Insert the columns into General_ATTRIBUTE_Tables
                foreach ($columnsArray as $column) {
                    $attributeId = DB::table('General_ATTRIBUTE_Tables')->insertGetId([
                        'table_id' => $tableId,
                        'attribute_name' => $column['column_name'],
                        'data_type' => $column['data_type'],
                        'is_primary_key' => $column['is_primary_key'],
                        'is_foreign_key' => $column['is_foreign_key'],
                        'timestamp_insert' => now()
                    ]);
    
                    // If the column is a primary key, insert into General_PKEY_Tables
                    if ($column['is_primary_key']) {
                        DB::table('General_PKEY_Tables')->insert([
                            'table_id' => $tableId,
                            'attribute_id' => $attributeId,
                            'constraint_name' => 'PK_' . $tableName . '_' . $column['column_name'],
                            'timestamp_insert' => now()
                        ]);
                    }
    
                    // If the column is a foreign key, insert into General_FKEY_Tables
                    if ($column['is_foreign_key']) {
                        $referenceTableId = DB::table('General_TABLE_Tables')
                            ->where('table_name', $column['reference_table'])
                            ->value('table_id');
    
                        $referenceAttributeId = DB::table('General_ATTRIBUTE_Tables')
                            ->where('table_id', $referenceTableId)
                            ->where('attribute_name', $column['reference_column'])
                            ->value('attribute_id');
    
                        DB::table('General_FKEY_Tables')->insert([
                            'table_id' => $tableId,
                            'attribute_id' => $attributeId,
                            'reference_table_id' => $referenceTableId,
                            'reference_attribute_id' => $referenceAttributeId,
                            'constraint_name' => 'FK_' . $tableName . '_' . $column['column_name'],
                            'timestamp_insert' => now()
                        ]);
                    }
                }
    
                // Commit the transaction
                DB::commit();
    
                return [
                    'sql' => [],
                    'bindings' => []
                ];
            } catch (\Exception $e) {
                // Rollback the transaction if any query fails
                DB::rollBack();
                throw new Exception('Transaction failed: ' . $e->getMessage());
            }
        }
    
        throw new Exception('Invalid CREATE TABLE query.');
    }



    private function insertDataIntoTable($query)
{
    // Extract the table name and values from the INSERT query
    if (preg_match('/INSERT\s+INTO\s+([a-zA-Z0-9_]+)\s*\(([^)]+)\)\s+VALUES\s*\(([^)]+)\)/i', $query, $matches)) {
        $tableName = $matches[1];
        $columns = $matches[2];
        $values = $matches[3];

        // Parse the columns and values
        $columnsArray = explode(',', $columns);
        $valuesArray = explode(',', $values);

        // Check if the table exists
        $tableExists = DB::table('General_TABLE_Tables')->where('table_name', $tableName)->exists();
        if (!$tableExists) {
            throw new Exception("Table '$tableName' does not exist.");
        }

        // Get the table ID
        $tableRecord = DB::table('General_TABLE_Tables')->where('table_name', $tableName)->first();
        $tableId = $tableRecord->table_id;

        // Prepare the attribute values as JSON
        $attributeValues = [];
        foreach ($columnsArray as $index => $column) {
            $attributeValues[trim($column)] = trim($valuesArray[$index]);
        }

        // Return the SQL queries and bindings
        return [
            'sql' => [
                'INSERT INTO General_VALUE_Tables (table_id, attribute_values, timestamp_insert) VALUES (?, ?, ?)'
            ],
            'bindings' => [
                [$tableId, json_encode($attributeValues), now()]
            ]
        ];
    }

    throw new Exception('Invalid INSERT query.');
}




private function modifyValue($query)
{
    // Extract the table name, columns, and values from the UPDATE query
    if (preg_match('/UPDATE\s+([a-zA-Z0-9_]+)\s+SET\s+([^W]+)\s+WHERE\s+([^;]+)/i', $query, $matches)) {
        $tableName = $matches[1];
        $setClause = $matches[2];
        $whereClause = $matches[3];

        // Parse the set clause
        $setArray = [];
        $setPattern = '/([a-zA-Z0-9_]+)\s*=\s*([^,]+)/i';
        if (preg_match_all($setPattern, $setClause, $setMatches, PREG_SET_ORDER)) {
            foreach ($setMatches as $setMatch) {
                $setArray[trim($setMatch[1])] = trim($setMatch[2], "'");
            }
        }

        // Parse the where clause
        $whereArray = [];
        $wherePattern = '/([a-zA-Z0-9_]+)\s*=\s*([^,]+)/i';
        if (preg_match_all($wherePattern, $whereClause, $whereMatches, PREG_SET_ORDER)) {
            foreach ($whereMatches as $whereMatch) {
                $whereArray[trim($whereMatch[1])] = trim($whereMatch[2], "'");
            }
        }

        // Check if the table exists
        $tableExists = DB::table('General_TABLE_Tables')->where('table_name', $tableName)->exists();
        if (!$tableExists) {
            throw new Exception("Table '$tableName' does not exist.");
        }

        // Get the table ID
        $tableRecord = DB::table('General_TABLE_Tables')->where('table_name', $tableName)->first();
        $tableId = $tableRecord->table_id;

        // Get the current attribute values
        $currentValues = DB::table('General_VALUE_Tables')
            ->where('table_id', $tableId)
            ->whereJsonContains('attribute_values', $whereArray)
            ->first();

        if (!$currentValues) {
            throw new Exception("No matching record found for update.");
        }

        $attributeValues = json_decode($currentValues->attribute_values, true);

        // Update the attribute values
        foreach ($setArray as $column => $value) {
            $attributeValues[$column] = $value;
        }

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Update the values in General_VALUE_Tables
            DB::table('General_VALUE_Tables')
                ->where('value_id', $currentValues->value_id)
                ->update([
                    'attribute_values' => json_encode($attributeValues),
                    'timestamp_insert' => now()
                ]);

            // Commit the transaction
            DB::commit();

            return [
                'sql' => 'Data updated successfully.',
                'bindings' => []
            ];
        } catch (\Exception $e) {
            // Rollback the transaction if any query fails
            DB::rollBack();
            throw new Exception('Transaction failed: ' . $e->getMessage());
        }
    }

    throw new Exception('Invalid UPDATE query.');
}



    


    

    
    




    private function showTables($query)
    {
        if (preg_match('/SHOW\s+TABLES\s)/i', $query )) {
            
            return [
                'sql' => 'SELECT * from GENERAL_BD_TABLES where db_name = ?',
                'bindings' => [$dbName]
            ];
        }
        throw new Exception('Invalid query.');
    }



    

































//     private function dropDatabase($query)
// {
//     if (preg_match('/DROP\s+DATABASE\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
//         $dbName = $matches[1];
        
//         return [
//             'sql' => [
//                 'DELETE FROM general_value_tables WHERE id_attr IN (
//                     SELECT id_attribute FROM general_attribute_tables WHERE id_table IN (
//                         SELECT id_table FROM general_table_tables WHERE db_id = (
//                             SELECT id_bd FROM general_bd_tables WHERE db_name = ?
//                         )
//                     )
//                 )',
//                 'DELETE FROM general_pkey_tables WHERE constraint_id IN (
//                     SELECT constraint_id FROM general_fkey_tables WHERE source_table_id IN (
//                         SELECT id_table FROM general_table_tables WHERE db_id = (
//                             SELECT id_bd FROM general_bd_tables WHERE db_name = ?
//                         )
//                     ) OR target_table_id IN (
//                         SELECT id_table FROM general_table_tables WHERE db_id = (
//                             SELECT id_bd FROM general_bd_tables WHERE db_name = ?
//                         )
//                     )
//                 )',
//                 'DELETE FROM general_fkey_tables WHERE source_table_id IN (
//                     SELECT id_table FROM general_table_tables WHERE db_id = (
//                         SELECT id_bd FROM general_bd_tables WHERE db_name = ?
//                     )
//                 ) OR target_table_id IN (
//                     SELECT id_table FROM general_table_tables WHERE db_id = (
//                         SELECT id_bd FROM general_bd_tables WHERE db_name = ?
//                     )
//                 )',
//                 'DELETE FROM general_attribute_tables WHERE id_table IN (
//                     SELECT id_table FROM general_table_tables WHERE db_id = (
//                         SELECT id_bd FROM general_bd_tables WHERE db_name = ?
//                     )
//                 )',
//                 'DELETE FROM general_table_tables WHERE db_id = (
//                     SELECT id_bd FROM general_bd_tables WHERE db_name = ?
//                 )',
//                 'DELETE FROM general_bd_tables WHERE db_name = ?'
//             ],
//             'bindings' => [
//                 $dbName, $dbName, $dbName, $dbName, $dbName, $dbName, $dbName
//             ]
//         ];
//     }
//     throw new Exception('Invalid DROP DATABASE query.');
// }
// private function createTable($query)
//     {
//         if (preg_match('/CREATE\s+T\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
//             $dbName = $matches[1];
//             return [
//                 'sql' => 'INSERT INTO General_BD_Tables (db_name, timestamp_insert) VALUES (?, NOW())',
//                 'bindings' => [$dbName]
//             ];
//         }
//         throw new Exception('Invalid CREATE DATABASE query.');
//     }
// // }
// private function DropDatabase($query)
// {
//     // Check if the query matches a DROP DATABASE statement
//     if (preg_match('/DROP\s+DATABASE\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
//         $dbName = $matches[1];

//         // Sanitize the database name (allow only alphanumeric and underscores)
//         $escapedDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);

//         // Initialize an array to store executed queries
//         $executedQueries = [];

//         // Begin a database transaction
//         DB::beginTransaction();

//         try {
//             // Delete foreign key attribute references
//             $query1 = "DELETE FROM general_fkey_attribute_tables WHERE constraint_id IN (SELECT id_bd FROM general_bd_tables WHERE db_name = ?)";
//             DB::statement($query1, [$escapedDbName]);
//             $executedQueries[] = $query1;

//             // Delete attributes related to the database
//             $query2 = "DELETE FROM general_attribute_tables WHERE id_table IN (SELECT id_bd FROM general_bd_tables WHERE db_name = ?)";
//             DB::statement($query2, [$escapedDbName]);
//             $executedQueries[] = $query2;

//             // Delete foreign key table references
//             $query3 = "DELETE FROM general_fkey_tables WHERE source_table_id IN (SELECT id_bd FROM general_bd_tables WHERE db_name = ?) OR target_table_id IN (SELECT id_bd FROM general_bd_tables WHERE db_name = ?)";
//             DB::statement($query3, [$escapedDbName, $escapedDbName]);
//             $executedQueries[] = $query3;

//             // Delete database metadata
//             $query4 = "DELETE FROM general_bd_tables WHERE db_name = ?";
//             DB::statement($query4, [$escapedDbName]);
//             $executedQueries[] = $query4;

//             // Drop the actual database
//             $query5 = "DROP DATABASE IF EXISTS $escapedDbName";
//             DB::statement($query5);
//             $executedQueries[] = $query5;

//             // Commit the transaction
//             DB::commit();

//             // Return success response
//             return response()->json([
//                 'message' => 'Database and associated records dropped successfully.',
//                 'queries' => $executedQueries,
//             ]);

//         } catch (\Exception $e) {
//             // Rollback the transaction if any query fails
//             DB::rollBack();

//             // Log the exception details
//             Log::error('Failed to execute DROP DATABASE query', [
//                 'exception_message' => $e->getMessage(),
//                 'exception_code' => $e->getCode(),
//                 'stack_trace' => $e->getTraceAsString(),
//             ]);

//             // Return an error response
//             return response()->json([
//                 'message' => 'Failed to drop the database.',
//                 'error' => $e->getMessage(),
//             ], 500);
//         }
//     } else {
//         return response()->json([
//             'message' => 'Invalid DROP DATABASE query format.',
//         ], 400);
//     }
// }

}