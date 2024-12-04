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
                        $tablesResponse = $this->getTables($dbRecord->db_id);
    
                        if ($tablesResponse->getStatusCode() == 200) {
                            $tables = json_decode($tablesResponse->getContent(), true)['tables'];
                            return response()->json([
                                'success' => true,
                                'db_name' => $dbName,
                                'db_id' => $dbRecord->db_id,
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
        }



        // Handle 'DROP DATABASE' queries
        elseif ($queryType == 'DROP_DATABASE') {
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


    // Handle 'ADD COLUMN' queries
    elseif ($queryType == 'ADD_COLUMN') {
        try {
            // Appeler la fonction pour obtenir les requêtes SQL et les bindings
            $addColumnResult = $this->addColumnToTable($userQuery);
    
            // Démarrer une transaction de base de données
            DB::beginTransaction();
    
            // Exécuter chaque requête SQL individuellement
            foreach ($addColumnResult['sql'] as $index => $sql) {
                // Si la requête n'a pas de bindings, ne pas les utiliser
                if (isset($addColumnResult['bindings'][$index])) {
                    DB::statement($sql, $addColumnResult['bindings'][$index]);
                } else {
                    DB::statement($sql); // Exécuter sans bindings
                }
            }
    
            // Commit de la transaction
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Column added successfully.'
            ]);
        } catch (\Exception $e) {
            // Rollback de la transaction en cas d'erreur
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    


    // Handle 'DROP attribut1' queries
    elseif ($queryType == 'DROP_COLUMN') {
        $dropResult = $this->dropColumnFromTable($userQuery);

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
                'message' => 'attribut1 dropped successfully.'
            ]);
        } catch (\Exception $e) {
            // Rollback the transaction if any query fails
            DB::rollBack();
            throw new Exception('Transaction failed: ' . $e->getMessage());
        }
    }


    elseif ($queryType == 'MODIFY_COLUMN') {
        $createResult = $this->modifyColumnInTable($userQuery);

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
    




        private function getQueryType($query)
        {
            if (preg_match('/^\s*CREATE\s+DATABASE/i', $query)) {
                return 'CREATE_DATABASE';
            } elseif (preg_match('/^\s*USE/i', $query)) {
                return 'USE';
            } elseif (preg_match('/^\s*ALTER\s+DATABASE/i', $query)) {
                return 'MODIFY_DATABASE';}
             elseif (preg_match('/^\s*DROP\s+DATABASE/i', $query)) {
                return 'DROP_DATABASE';
            } elseif (preg_match('/^\s*CREATE\s+TABLE/i', $query)) {
                return 'CREATE_TABLE';
            } elseif (preg_match('/^\s*SHOW\s+TABLES/i', $query)) {
                return 'SHOW_TABLES';
            } elseif (preg_match('/^\s*DROP\s+TABLE/i', $query)) {
                return 'DROP_TABLE';
            } elseif (preg_match('/ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+RENAME\s+TO\s+([a-zA-Z0-9_]+)/i', $query)) {
                return 'MODIFY_TABLE';
            } elseif (preg_match('/^\s*INSERT\s+INTO/i', $query)) {
                return 'INSERT_VALUES';
            } elseif (preg_match('/^\s*DELETE\s+FROM/i', $query)) {
                return 'DELETE_VALUES';
            } elseif (preg_match('/^\s*SELECT\s+/i', $query)) {
                return 'SELECT_VALUES';
            } elseif (preg_match('/^\s*UPDATE\s+([a-zA-Z0-9_]+)\s+SET\s+/i', $query)) {
                return 'UPDATE_VALUES';
            } elseif (preg_match('/^\s*ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+ADD\s+COLUMN/i', $query)) {
                return 'ADD_COLUMN';
            } elseif (preg_match('/^\s*ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+MODIFY\s+COLUMN\s+([a-zA-Z0-9_]+)\s+[a-zA-Z0-9()]+/i', $query)) {
                return 'MODIFY_COLUMN';
            } elseif (preg_match('/^\s*ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+DROP\s+COLUMN\s+([a-zA-Z0-9_]+)/i', $query)) {
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
    
        // ok
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


// ok
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



// ok
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



    // ok
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
        throw new Exception('Invalid ALTER TABLE query.');
    }


    
   // ok
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
    
            
    
// o?
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
                    'DELETE FROM General_pKEY_Tables WHERE table_id IN (SELECT table_id FROM General_TABLE_Tables WHERE db_id = (SELECT db_id FROM General_BD_Tables WHERE db_name = ?))',
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


// ok
    private function dropTable($query)
    {
        // Extract the table name from the DROP TABLE query
        if (preg_match('/DROP\s+TABLE\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
            $tableName = $matches[1];
    
            // Check if the table exists
            $tableRecord = DB::table('General_TABLE_Tables')->where('table_name', $tableName)->first();
            if (!$tableRecord) {
                throw new Exception("Table '$tableName' does not exist.");
            }
    
            $tableId = $tableRecord->table_id;
    
            // Start a transaction to ensure data integrity
            DB::beginTransaction();
    
            try {
                // Delete foreign key constraints
                DB::table('General_FKEY_Tables')->where('table_id', $tableId)->delete();
    
                // Delete primary key constraints
                DB::table('General_PKEY_Tables')->where('table_id', $tableId)->delete();
    
                // Delete all values associated with the table's attributes
                $attributes = DB::table('General_ATTRIBUTE_Tables')->where('table_id', $tableId)->get();
                foreach ($attributes as $attribute) {
                    DB::table('General_VALUE_Tables')->where('id_attr', $attribute->attribute_id)->delete();
                }
    
                // Delete attributes
                DB::table('General_ATTRIBUTE_Tables')->where('table_id', $tableId)->delete();
    
                // Finally, delete the table itself
                DB::table('General_TABLE_Tables')->where('table_id', $tableId)->delete();
    
                // Commit the transaction
                DB::commit();
    
                // Return success response
                return [
                    'sql' =>[],
                    'success' => true,
                    'message' => "Table '$tableName' dropped successfully."
                ];
            } catch (\Exception $e) {
                // Rollback on error
                DB::rollBack();
                throw new Exception('Transaction failed: ' . $e->getMessage());
            }
        }
    
        throw new Exception('Invalid DROP TABLE query.');
    }
    





// ok
private function createTable($query)
{
    // Step 1: Parse the CREATE TABLE query to extract table name and column definitions
    if (preg_match('/CREATE\s+TABLE\s+([a-zA-Z0-9_]+)\s*\((.+)\)/is', $query, $matches)) {
        $tableName = $matches[1];  // Extract the table name
        $columnsDefinition = $matches[2];  // Extract the column definitions

        // Step 2: Parse the columns (including data types and constraints)
        $columns = [];
        $columnPattern = '/([a-zA-Z0-9_]+)\s+([a-zA-Z0-9_]+)(?:\s+PRIMARY\s+KEY)?(?:\s+FOREIGN\s+KEY\s+REFERENCES\s+([a-zA-Z0-9_]+)\s*\(([a-zA-Z0-9_]+)\))?/i';
        preg_match_all($columnPattern, $columnsDefinition, $columnMatches, PREG_SET_ORDER);

        foreach ($columnMatches as $columnMatch) {
            $columns[] = [
                'column_name' => $columnMatch[1],
                'data_type' => $columnMatch[2],
                'is_primary_key' => isset($columnMatch[0]) && strpos($columnMatch[0], 'PRIMARY KEY') !== false,
                'is_foreign_key' => isset($columnMatch[0]) && strpos($columnMatch[0], 'FOREIGN KEY') !== false,
                'reference_table' => $columnMatch[3] ?? null,
                'reference_column' => $columnMatch[4] ?? null,
            ];
        }

        // Step 3: Ensure the database is selected
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

        // Step 4: Start a transaction to insert the table and columns
        DB::beginTransaction();

        try {
            // Insert the table into the General_TABLE_Tables
            $tableId = DB::table('General_TABLE_Tables')->insertGetId([
                'db_id' => $dbId,
                'table_name' => $tableName,
                'timestamp_insert' => now()
            ]);

            // Step 5: Insert each column into General_ATTRIBUTE_Tables and handle constraints
            foreach ($columns as $column) {
                $attributeId = DB::table('General_ATTRIBUTE_Tables')->insertGetId([
                    'table_id' => $tableId,
                    'attribute_name' => $column['column_name'],
                    'data_type' => $column['data_type'],
                    'is_primary_key' => $column['is_primary_key'],
                    'is_foreign_key' => $column['is_foreign_key'],
                    'timestamp_insert' => now()
                ]);

                // Step 6: Handle primary key constraint
                if ($column['is_primary_key']) {
                    DB::table('General_PKEY_Tables')->insert([
                        'table_id' => $tableId,
                        'attribute_id' => $attributeId,
                        'constraint_name' => 'PK_' . $tableName . '_' . $column['column_name'],
                        'timestamp_insert' => now()
                    ]);
                }

                // Step 7: Handle foreign key constraint
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

            // Commit the transaction if everything is successful
            DB::commit();
            return [
                'sql' => [],
                'bindings' => []
            ];
        } catch (\Exception $e) {
            // Rollback if an error occurs
            DB::rollBack();
            throw new Exception('Transaction failed: ' . $e->getMessage());
        }
    }

    throw new Exception('Invalid CREATE TABLE query.');
}





// ok
private function insertDataIntoTable($query)
{
    // Étape 1 : Analyser la requête INSERT INTO
    if (preg_match('/INSERT\s+INTO\s+([a-zA-Z0-9_]+)\s*\(([^)]+)\)\s+VALUES\s*\(([^)]+)\)/i', $query, $matches)) {
        $tableName = $matches[1]; // Nom de la table
        $columns = explode(',', str_replace(' ', '', $matches[2])); // Colonnes
        $values = explode(',', str_replace(' ', '', $matches[3])); // Valeurs

        // Vérifier si les colonnes et les valeurs correspondent
        if (count($columns) !== count($values)) {
            throw new Exception("Le nombre de colonnes ne correspond pas au nombre de valeurs.");
        }

        // Étape 2 : Vérifier que la table existe
        $dbName = session('selected_db');
        if (!$dbName) {
            throw new Exception('Aucune base de données sélectionnée. Utilisez la commande "USE" pour sélectionner une base.');
        }

        $dbRecord = DB::table('General_BD_Tables')->where('db_name', $dbName)->first();
        if (!$dbRecord) {
            throw new Exception("La base de données '$dbName' n'existe pas.");
        }

        $tableRecord = DB::table('General_TABLE_Tables')->where('table_name', $tableName)->first();
        if (!$tableRecord) {
            throw new Exception("La table '$tableName' n'existe pas dans la base '$dbName'.");
        }

        // Récupérer l'ID de la table
        $tableId = $tableRecord->table_id;

        // Étape 3 : Associer les colonnes avec leurs ID dans General_ATTRIBUTE_Tables
        $attributes = DB::table('General_ATTRIBUTE_Tables')->where('table_id', $tableId)->get()->keyBy('attribute_name');
        $dataToInsert = [];

        foreach ($columns as $index => $columnName) {
            if (!isset($attributes[$columnName])) {
                throw new Exception("La colonne '$columnName' n'existe pas dans la table '$tableName'.");
            }

            $attributeId = $attributes[$columnName]->attribute_id;
            $dataToInsert[] = [
                'id_attr' => $attributeId,
                'attribute_values' => trim($values[$index], "'\""), // Supprimer les guillemets autour des valeurs
                'timestamp_insert' => now()
            ];
        }

        // Étape 4 : Insérer les données dans General_VALUE_Tables
        DB::beginTransaction();

        try {
            foreach ($dataToInsert as $dataRow) {
                DB::table('General_VALUE_Tables')->insert($dataRow);
            }

            DB::commit();
            return [
                'sql' => [],
                'success' => true,
                'message' => "Les données ont été insérées avec succès dans la table '$tableName'."
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw new Exception('Échec de la transaction : ' . $e->getMessage());
        }
    }

    throw new Exception('Requête INSERT INTO invalide.');
}







// --------------------------------------------------------------------------------------

private function modifyValue($query)
{
    // Extraire le nom de la table, les colonnes SET et les conditions WHERE
    if (preg_match('/UPDATE\s+([a-zA-Z0-9_]+)\s+SET\s+([^W]+)\s+WHERE\s+(.+)/i', $query, $matches)) {
        $tableName = $matches[1];
        $setClause = $matches[2];
        $whereClause = $matches[3];

        // Analyser la clause SET
        $setArray = [];
        $setPattern = '/([a-zA-Z0-9_]+)\s*=\s*[\'"]?([^\'"]+)[\'"]?/i';
        if (preg_match_all($setPattern, $setClause, $setMatches, PREG_SET_ORDER)) {
            foreach ($setMatches as $setMatch) {
                $setArray[trim($setMatch[1])] = trim($setMatch[2]);
            }
        }

        // Analyser la clause WHERE
        $whereArray = [];
        $wherePattern = '/([a-zA-Z0-9_]+)\s*=\s*[\'"]?([^\'"]+)[\'"]?/i';
        if (preg_match_all($wherePattern, $whereClause, $whereMatches, PREG_SET_ORDER)) {
            foreach ($whereMatches as $whereMatch) {
                $whereArray[trim($whereMatch[1])] = trim($whereMatch[2]);
            }
        }

        // Vérifier si la table existe
        $tableExists = DB::table('General_TABLE_Tables')->where('table_name', $tableName)->exists();
        if (!$tableExists) {
            throw new Exception("Table '$tableName' does not exist.");
        }

        // Récupérer l'ID de la table
        $tableId = DB::table('General_TABLE_Tables')
            ->where('table_name', $tableName)
            ->value('table_id');

        // Vérifier si les colonnes SET et WHERE existent
        foreach (array_merge(array_keys($setArray), array_keys($whereArray)) as $column) {
            $attributeExists = DB::table('General_ATTRIBUTE_Tables')
                ->where('table_id', $tableId)
                ->where('attribute_name', $column)
                ->exists();

            if (!$attributeExists) {
                throw new Exception("Column '$column' does not exist in table '$tableName'.");
            }
        }

        // Appliquer les mises à jour
        DB::beginTransaction();

        try {
            // Mettre à jour les valeurs
            foreach ($setArray as $column => $value) {
                // Obtenir l'ID de l'attribut à modifier
                $attributeId = DB::table('General_ATTRIBUTE_Tables')
                    ->where('table_id', $tableId)
                    ->where('attribute_name', $column)
                    ->value('attribute_id');

                // Construire la requête de mise à jour
                $updateQuery = DB::table('General_VALUE_Tables')
                    ->where('id_attr', $attributeId);

                // Ajouter les conditions WHERE
                foreach ($whereArray as $whereColumn => $whereValue) {
                    $whereAttributeId = DB::table('General_ATTRIBUTE_Tables')
                        ->where('table_id', $tableId)
                        ->where('attribute_name', $whereColumn)
                        ->value('attribute_id');

                    $updateQuery->where('id_attr', $whereAttributeId)
                        ->where('attribute_values', $whereValue);
                }

                // Effectuer la mise à jour
                $updateQuery->update([
                    'attribute_values' => $value,
                    'timestamp_insert' => now(),
                ]);
            }

            DB::commit();

            return [
                'sql'=>[],
                'success' => true,
                'message' => "Values updated successfully in table '$tableName'."
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw new Exception('Transaction failed: ' . $e->getMessage());
        }
    }

    throw new Exception('Invalid UPDATE query.');
}















// ok
    private function addColumnToTable($query)
{
    // Extraire le nom de la base de données sélectionnée
    $dbName = session('selected_db');
    if (!$dbName) {
        throw new Exception('No database selected.');
    }

    // Vérifier si la base de données existe
    $dbRecord = DB::table('General_BD_Tables')
        ->where('db_name', $dbName)
        ->first();

    if (!$dbRecord) {
        throw new Exception("Database '$dbName' not found.");
    }

    // Extraire les détails de la requête
    if (preg_match('/^\s*ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+ADD\s+COLUMN\s+([a-zA-Z0-9_]+)\s+([a-zA-Z0-9()]+(?:\([^\)]*\))?)\s*;?/i', $query, $matches)) {
        $tableName = $matches[1];
        $columnName = $matches[2];
        $dataType = $matches[3];

        // Vérifier si la table existe
        $tableRecord = DB::table('General_TABLE_Tables')
            ->where('table_name', $tableName)
            ->where('db_id', $dbRecord->db_id)
            ->first();

        if (!$tableRecord) {
            throw new Exception("Table '$tableName' does not exist in database '$dbName'.");
        }

        // Vérifier si la colonne existe déjà
        $columnExists = DB::table('General_ATTRIBUTE_Tables')
            ->where('table_id', $tableRecord->table_id)
            ->where('attribute_name', $columnName)
            ->exists();

        if ($columnExists) {
            throw new Exception("Column '$columnName' already exists in table '$tableName'.");
        }

        // Début de la transaction
        DB::beginTransaction();

        try {
            // Ajout physique de la colonne dans la table
            // DB::statement("ALTER TABLE $tableName ADD COLUMN $columnName $dataType");

            // Mise à jour des métadonnées
            DB::table('General_ATTRIBUTE_Tables')->insert([
                'table_id' => $tableRecord->table_id,
                'attribute_name' => $columnName,
                'data_type' => $dataType,
                'timestamp_insert' => now()
            ]);

            // Commit de la transaction
            DB::commit();

            return [
                'sql'=>[],
                'success' => true,
                'message' => "Column '$columnName' added successfully to table '$tableName'."
            ];
        } catch (\Exception $e) {
            // Rollback de la transaction en cas d'erreur
            DB::rollBack();
            throw new Exception('Transaction failed: ' . $e->getMessage());
        }
    }

    throw new Exception('Invalid ADD COLUMN query.');
}

    



    








// ok
private function modifyColumnInTable($query)
{
    // Récupérer le nom de la base de données sélectionnée
    $dbName = session('selected_db');
    $dbRecord = DB::table('General_BD_Tables')->where('db_name', $dbName)->first();

    if (!$dbRecord) {
        throw new Exception("Database '$dbName' does not exist.");
    }

    // Analyser la requête pour extraire les informations
    if (preg_match('/^\s*ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+MODIFY\s+COLUMN\s+([a-zA-Z0-9_]+)\s+([a-zA-Z0-9()]+)(.*)$/i', $query, $matches)) {
        $tableName = $matches[1];
        $attributeName = $matches[2];
        $newDataType = $matches[3];
        $extraAttributes = trim($matches[4]);

        // Vérifier si la table existe
        $tableRecord = DB::table('General_TABLE_Tables')
            ->where('table_name', $tableName)
            ->where('db_id', $dbRecord->db_id)
            ->first();

        if (!$tableRecord) {
            throw new Exception("Table '$tableName' does not exist in the database '$dbName'.");
        }

        $tableId = $tableRecord->table_id;

        // Vérifier si la colonne existe
        $attributeRecord = DB::table('General_ATTRIBUTE_Tables')
            ->where('table_id', $tableId)
            ->where('attribute_name', $attributeName)
            ->first();

        if (!$attributeRecord) {
            throw new Exception("Column '$attributeName' does not exist in table '$tableName'.");
        }

        $attributeId = $attributeRecord->attribute_id;

        // Construire les requêtes SQL pour modifier la colonne
        return [
            'sql' => [
                'UPDATE General_ATTRIBUTE_Tables SET data_type = ?, is_primary_key = ?, is_foreign_key = ? WHERE attribute_id = ?;',
                'UPDATE General_VALUE_Tables SET timestamp_insert = ? WHERE id_attr = ?;'
            ],
            'bindings' => [
                [
                    $newDataType,
                    strpos(strtoupper($extraAttributes), 'PRIMARY KEY') !== false ? 1 : 0,
                    strpos(strtoupper($extraAttributes), 'FOREIGN KEY') !== false ? 1 : 0,
                    $attributeId
                ],
                [
                    now(),
                    $attributeId
                ]
            ]
        ];
    }

    throw new Exception('Invalid MODIFY COLUMN query.');
}







// ok
private function dropColumnFromTable($query)
{
    // Récupérer le nom de la base de données sélectionnée
    $dbName = session('selected_db');
    $dbRecord = DB::table('General_BD_Tables')->where('db_name', $dbName)->first();

    if (!$dbRecord) {
        throw new Exception("Database '$dbName' does not exist.");
    }

    // Analyser la requête pour extraire le nom de la table et de la colonne
    if (preg_match('/^\s*ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+DROP\s+COLUMN\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
        $tableName = $matches[1];
        $attributeName = $matches[2];

        // Vérifier si la table existe
        $tableRecord = DB::table('General_TABLE_Tables')
            ->where('table_name', $tableName)
            ->where('db_id', $dbRecord->db_id)
            ->first();

        if (!$tableRecord) {
            throw new Exception("Table '$tableName' does not exist in the database '$dbName'.");
        }

        $tableId = $tableRecord->table_id;

        // Vérifier si la colonne existe
        $attributeRecord = DB::table('General_ATTRIBUTE_Tables')
            ->where('table_id', $tableId)
            ->where('attribute_name', $attributeName)
            ->first();

        if (!$attributeRecord) {
            throw new Exception("Column '$attributeName' does not exist in table '$tableName'.");
        }

        $attributeId = $attributeRecord->attribute_id;

        // Construire les requêtes SQL pour supprimer la colonne et ses dépendances
        return [
            'sql' => [
                'DELETE FROM General_ATTRIBUTE_Tables WHERE attribute_name = ? AND table_id = ?;',
                'DELETE FROM General_VALUE_Tables WHERE id_attr = ?;',
                'DELETE FROM General_FKEY_Tables WHERE attribute_id = ? OR reference_attribute_id = ?;',
                'DELETE FROM General_PKEY_Tables WHERE attribute_id = ?;'
            ],
            'bindings' => [
                [$attributeName, $tableId],
                [$attributeId],
                [$attributeId, $attributeId],
                [$attributeId]
            ]
        ];
    }

    throw new Exception('Invalid DROP COLUMN query.');
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












    ::::://////////////
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
        $databases = DB::table('General_BD_TABLES')->pluck('db_name', 'id_bd');
        
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
            }elseif ($queryType == 'MODIFY_TABLE') {
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
            }elseif ($queryType == 'SELECT_VALUES') {
                try {
                    $result = $this->selectTableContent($userQuery);
            
                    return response()->json([
                        'success' => true,
                        'columns' => $result['columns'],
                        'data' => $result['data']
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error: ' . $e->getMessage(),
                    ], 500);
                }
            }elseif ($queryType == 'DELETE_VALUES') {
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
            }elseif ($queryType == 'CREATE_TABLE') {
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
            }elseif ($queryType == 'DROP_TABLE') {
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
            }elseif ($queryType == 'INSERT_VALUES') {
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
            }elseif ($queryType == 'UPDATE_VALUES') {
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
            }elseif ($queryType == 'DROP_PRIMARY_KEY') {
                // Handle DROP PRIMARY KEY queries
                $message = $this->dropPrimaryKey($userQuery);
                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            }elseif ($queryType == 'ADD_FOREIGN_KEY') {
                $resultMessage = $this->addForeignKey($userQuery);
                return response()->json([
                    'success' => true,
                    'message' => $resultMessage
                ]);
            }
            elseif ($queryType == 'MODIFY_FOREIGN_KEY') {
                // Call the dropForeignKey function with the matched query
                $message= $this->dropForeignKey($userQuery);
                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            }
            // elseif ($queryType == 'SELECT_FOREIGN_KEY'){
            //     try {
            //         $result = $this->selectForeignKeyContent($userQuery); // function to fetch foreign key info
        
            //         // Returning the result as JSON response for frontend to handle
            //         return response()->json([
            //             'success' => true,
            //             'columns' => $result['columns'], // Table column headers (array)
            //             'data' => $result['data'] // Actual data for foreign keys (array of rows)
            //         ]);
            //     } catch (\Exception $e) {
            //         return response()->json([
            //             'success' => false,
            //             'message' => 'Error: ' . $e->getMessage(),
            //         ], 500);
            //     }
            // }
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
    } elseif (preg_match('/ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+RENAME\s+TO\s+([a-zA-Z0-9_]+)/i', $query)) {
        return 'MODIFY_TABLE';
    } elseif (preg_match('/^\s*INSERT\s+INTO/i', $query)) {
        return 'INSERT_VALUES';
    } elseif (preg_match('/^\s*DELETE\s+FROM/i', $query)) {
        return 'DELETE_VALUES';
    } elseif (preg_match('/^\s*SELECT\s+/i', $query)) {
    // {if (preg_match('/SELECT\s+\*\s+FROM\s+fkey/i', $query)) {
    //     return 'SELECT_FOREIGN_KEY';
    // }
    // else{
        return 'SELECT_VALUES';
    } elseif (preg_match('/^\s*UPDATE\s+SET\s+/i', $query)) {
        return 'UPDATE_VALUES';
    } elseif (preg_match('/^\s*ALTER\s+TABLE\s+ADD\s+COLUMN/i', $query)) {
        return 'ADD_COLUMN';
    } elseif (preg_match('/ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+MODIFY\s+COLUMN\s+([a-zA-Z0-9_]+)\s+([\w\(\)]+)/i', $query)) {
        return 'MODIFY_COLUMN';
    } elseif (preg_match('/^\s*ALTER\s+TABLE\s+DROP\s+COLUMN/i', $query)) {
        return 'DROP_COLUMN';
    } elseif (preg_match('/ALTER\s+TABLE\s+(\w+)\s+ADD\s+PRIMARY\s+KEY\s*\(\s*(\w+)\s*\)/i', $query)) {
        return 'ADD_PRIMARY_KEY';
    } elseif (preg_match('/ALTER\s+TABLE\s+(\w+)\s+ADD\s+CONSTRAINT\s+(\w+)\s+FOREIGN\s+KEY\s*\((\w+)\)\s+REFERENCES\s+(\w+)\s*\((\w+)\)/i', $query)) {
        return 'ADD_FOREIGN_KEY';
    } elseif (preg_match('/ALTER\s+TABLE\s+(\w+)\s+DROP\s+PRIMARY\s+KEY(\s+IF\s+EXISTS\s+(\w+))?/i', $query)) {
        return 'SELECT_PRIMARY_KEY';
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
                    return $this->dropPrimaryKey($query);
                case 'DROP_FOREIGN_KEY':
                    return $this->deleteForeignKey($query);
                case 'SELECT_VALUES':
                    return $this->selectTableContent($query);
                case 'MODIFY_PRIMARY_KEY':
                    return $this->modifyPrimaryKey($query);
                case 'MODIFY_FOREIGN_KEY':
                    return $this->modifyForeignKey($query);
                    case 'MODIFY_PRIMARY_KEY':
                    return $this->modifyPrimaryKey($query);
                case 'MODIFY_FOREIGN_KEY':
                    return $this->modifyForeignKey($query);
                case 'MODIFY_PRIMARY_KEY':
                    return $this->modifyPrimaryKey($query);
                case 'MODIFY_FOREIGN_KEY':
                    return $this->modifyForeignKey($query);
                case 'SELECT_PRIMARY_KEY':
                    return $this->modifyPrimaryKey($query);
                case 'SELECT_FOREIGN_KEY':
                    return $this->selectForeignKeyContent($query);
                default:
                    return response()->json(['success' => false, 'message' => 'Unsupported query type.'], 400);
            }
        }
    
        
        private function useDatabase($query)
    {
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
                'bindings' => [$newTableName,$oldTableName,$dbRecord->id_bd]
            ];
        }
        throw new Exception('Invalid ALTER DATABASE query.');
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
                    'DELETE FROM General_FKEY_Tables WHERE table_id IN (SELECT table_id FROM General_TABLE_Tables WHERE db_id = (SELECT id_bd FROM General_BD_Tables WHERE db_name = ?))',
                    'DELETE FROM General_ATTRIBUTE_Tables WHERE table_id IN (SELECT table_id FROM General_TABLE_Tables WHERE db_id = (SELECT id_bd FROM General_BD_Tables WHERE db_name = ?))',
                    'DELETE FROM General_VALUE_Tables WHERE table_id IN (SELECT table_id FROM General_TABLE_Tables WHERE db_id = (SELECT id_bd FROM General_BD_Tables WHERE db_name = ?))',
                    'DELETE FROM General_TABLE_Tables WHERE db_id = (SELECT id_bd FROM General_BD_Tables WHERE db_name = ?)',
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
    }
    private function createTable($query)
    {
        // Step 1: Parse the CREATE TABLE query to extract table name and column definitions
        if (preg_match('/CREATE\s+TABLE\s+([a-zA-Z0-9_]+)\s*\((.+)\)/is', $query, $matches)) {
            $tableName = $matches[1];  // Extract the table name
            $columnsDefinition = $matches[2];  // Extract the column definitions
    
            // Step 2: Parse the columns (including data types and constraints)
            $columns = [];
            $columnPattern = '/([a-zA-Z0-9_]+)\s+([a-zA-Z0-9_]+)(?:\s+PRIMARY\s+KEY)?(?:\s+FOREIGN\s+KEY\s+REFERENCES\s+([a-zA-Z0-9_]+)\s*\(([a-zA-Z0-9_]+)\))?/i';
            preg_match_all($columnPattern, $columnsDefinition, $columnMatches, PREG_SET_ORDER);
    
            foreach ($columnMatches as $columnMatch) {
                $columns[] = [
                    'column_name' => $columnMatch[1],
                    'data_type' => $columnMatch[2],
                    'is_primary_key' => isset($columnMatch[0]) && strpos($columnMatch[0], 'PRIMARY KEY') !== false,
                    'is_foreign_key' => isset($columnMatch[0]) && strpos($columnMatch[0], 'FOREIGN KEY') !== false,
                    'reference_table' => $columnMatch[3] ?? null,
                    'reference_column' => $columnMatch[4] ?? null,
                ];
            }
    
            // Step 3: Ensure the database is selected
            $dbName = session('selected_db');
            if (!$dbName) {
                throw new Exception('No database selected. Use the "USE" command to select a database.');
            }
    
            // Get the database ID
            $dbRecord = DB::table('General_BD_Tables')->where('db_name', $dbName)->first();
            if (!$dbRecord) {
                throw new Exception("Database '$dbName' does not exist.");
            }
            $dbId = $dbRecord->id_bd;
    
            // Step 4: Start a transaction to insert the table and columns
            DB::beginTransaction();
    
            try {
                // Insert the table into the General_TABLE_Tables
                $tableId = DB::table('General_TABLE_Tables')->insertGetId([
                    'db_id' => $dbId,
                    'table_name' => $tableName,
                    'timestamp_insert' => now()
                ]);
    
                // Step 5: Insert each column into General_ATTRIBUTE_Tables and handle constraints
                foreach ($columns as $column) {
                    $attributeId = DB::table('General_ATTRIBUTE_Tables')->insertGetId([
                        'table_id' => $tableId,
                        'attribute_name' => $column['column_name'],
                        'data_type' => $column['data_type'],
                        'is_primary_key' => $column['is_primary_key'],
                        'is_foreign_key' => $column['is_foreign_key'],
                        'timestamp_insert' => now()
                    ]);
    
                    // Step 6: Handle primary key constraint
                    if ($column['is_primary_key']) {
                        DB::table('General_PKEY_Tables')->insert([
                            'table_id' => $tableId,
                            'attribute_id' => $attributeId,
                            'constraint_name' => 'PK_' . $tableName . '_' . $column['column_name'],
                            'timestamp_insert' => now()
                        ]);
                    }
    
                    // Step 7: Handle foreign key constraint
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
    
                // Commit the transaction if everything is successful
                DB::commit();
                return [
                    'sql' => [],
                    'bindings' => []
                ];
            } catch (\Exception $e) {
                // Rollback if an error occurs
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
// private function selectTableContent($query)
// {
//     $dbName = session('selected_db');
//     $dbRecord = DB::table('general_bd_tables')
//                   ->where('db_name', $dbName)
//                   ->first();

//     // Ensure a database has been selected
//     if (!$dbName) {
//         throw new Exception('No database selected. Use the "USE" command to select a database.');
//     }

//     // Extract the table name from the query
//     if (preg_match('/SELECT\s+\*\s+FROM\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
//         $tableName = $matches[1];

//         // Retrieve the table record to get the table ID
//         $tableRecord = DB::select('SELECT table_id FROM general_table_tables WHERE db_id = ? AND table_name = ?', [$dbRecord->id_bd, $tableName]);

//         if (empty($tableRecord)) {
//             throw new Exception('Table not found in the selected database.');
//         }

//         $tableId = $tableRecord[0]->table_id;

//         // Fetch column metadata for the table
//         $columns = DB::select('SELECT attribute_id, attribute_name FROM general_attribute_tables WHERE table_id = ? ORDER BY attribute_id', [$tableId]);

//         if (empty($columns)) {
//             throw new Exception('No columns found for this table.');
//         }

//         // Initialize the result array for organized data
//         $organizedData = [];

//         // For each column, get the data from 'general_value_tables'
//         foreach ($columns as $column) {
//             // Fetch data for this column based on its id_attribute
//             $columnData = DB::select('SELECT attribute_values FROM general_value_tables WHERE id_attr = ?', [$column->attribute_id]);

//             // Organize the column data (assuming value column contains the actual data)
//             $organizedData[$column->attribute_name] = array_map(function ($row) {
//                 return $row->attribute_values;  // Adjust according to the column data structure
//             }, $columnData);
//         }

//         // Return the organized data with column names and data
//         return [
//             'columns' => array_keys($organizedData), // Column names
//             'data' => $organizedData // Organized data for each column
//         ];
//     }

//     throw new Exception('Invalid SELECT query format.');
// }
private function selectTableContent($query)
{
    $dbName = session('selected_db');
    $dbRecord = DB::table('general_bd_tables')
                  ->where('db_name', $dbName)
                  ->first();

    // Ensure a database has been selected
    if (!$dbName) {
        throw new Exception('No database selected. Use the "USE" command to select a database.');
    }

    if (preg_match('/SELECT\s+\*\s+FROM\s+fkey/i', $query)) {
        // Fetch foreign key information from 'general_fkey_tables'
        $fkeyRecords = DB::select('SELECT fk.fkey_id, fk.constraint_name, fk.table_id, fk.reference_table_id, fk.attribute_id, fk.reference_attribute_id
                                   FROM general_fkey_tables fk
                                   INNER JOIN general_table_tables gt ON fk.table_id = gt.table_id
                                   INNER JOIN general_attribute_tables ga ON fk.attribute_id = ga.attribute_id
                                   WHERE gt.db_id = ?',
                                   [$dbRecord->id_bd]);

        if (empty($fkeyRecords)) {
            throw new Exception('No foreign keys found in the selected database.');
        }

        // Initialize arrays for columns and data
        $columns = [
            'fk_name',
            'table_name',
            'attribute_name',
            'reference_table_name',
            'reference_attribute_name'
        ];

        // Initialize data arrays for each column
        $data = [
            'fk_name' => [],
            'table_name' => [],
            'attribute_name' => [],
            'reference_table_name' => [],
            'reference_attribute_name' => []
        ];

        // Iterate over foreign key records and organize data
        foreach ($fkeyRecords as $fkey) {
            // Fetch table names and attribute names using the respective IDs
            $tableName = DB::table('general_table_tables')
                ->where('table_id', $fkey->table_id)
                ->value('table_name');

            $attributeName = DB::table('general_attribute_tables')
                ->where('attribute_id', $fkey->attribute_id)
                ->value('attribute_name');

            $referenceTableName = DB::table('general_table_tables')
                ->where('table_id', $fkey->reference_table_id)
                ->value('table_name');

            $referenceAttributeName = DB::table('general_attribute_tables')
                ->where('attribute_id', $fkey->reference_attribute_id)
                ->value('attribute_name');

            // Append data to the respective columns
            $data['fk_name'][] = $fkey->constraint_name;
            $data['table_name'][] = $tableName;
            $data['attribute_name'][] = $attributeName;
            $data['reference_table_name'][] = $referenceTableName;
            $data['reference_attribute_name'][] = $referenceAttributeName;
        }

        // Return the columns and data in the same format as the second case
        return [
            'success' => true,
            'columns' => $columns,
            'data' => $data
        ];
    } elseif (preg_match('/SELECT\s+\*\s+FROM\s+pkey/i', $query)) {
        // Fetch primary key information from 'general_pkey_tables'
        $pkeyRecords = DB::select('SELECT pk.pkey_id, pk.constraint_name, pk.table_id, pk.attribute_id
                                   FROM general_pkey_tables pk
                                   INNER JOIN general_table_tables gt ON pk.table_id = gt.table_id
                                   INNER JOIN general_attribute_tables ga ON pk.attribute_id = ga.attribute_id
                                   WHERE gt.db_id = ?',
                                   [$dbRecord->id_bd]);

        if (empty($pkeyRecords)) {
            throw new Exception('No primary keys found in the selected database.');
        }

        // Initialize arrays for columns and data
        $columns = [
            'pkey_name',
            'table_name',
            'attribute_name'
        ];

        // Initialize data arrays for each column
        $data = [
            'pkey_name' => [],
            'table_name' => [],
            'attribute_name' => []
        ];

        // Iterate over primary key records and organize data
        foreach ($pkeyRecords as $pkey) {
            // Fetch table names and attribute names using the respective IDs
            $tableName = DB::table('general_table_tables')
                ->where('table_id', $pkey->table_id)
                ->value('table_name');

            $attributeName = DB::table('general_attribute_tables')
                ->where('attribute_id', $pkey->attribute_id)
                ->value('attribute_name');

            // Append data to the respective columns
            $data['pkey_name'][] = $pkey->constraint_name;
            $data['table_name'][] = $tableName;
            $data['attribute_name'][] = $attributeName;
        }

        // Return the columns and data in the same format as the second case
        return [
            'success' => true,
            'columns' => $columns,
            'data' => $data
        ];
    }elseif (preg_match('/SELECT\s+\*\s+FROM\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
        // This is a regular SELECT query for table content
        $tableName = $matches[1];

        // Retrieve the table record to get the table ID
        $tableRecord = DB::select('SELECT table_id FROM general_table_tables WHERE db_id = ? AND table_name = ?', [$dbRecord->id_bd, $tableName]);

        if (empty($tableRecord)) {
            throw new Exception('Table not found in the selected database.');
        }

        $tableId = $tableRecord[0]->table_id;

        // Fetch column metadata for the table
        $columns = DB::select('SELECT attribute_id, attribute_name FROM general_attribute_tables WHERE table_id = ? ORDER BY attribute_id', [$tableId]);

        if (empty($columns)) {
            throw new Exception('No columns found for this table.');
        }

        // Initialize the result array for organized data
        $organizedData = [];

        // For each column, get the data from 'general_value_tables'
        foreach ($columns as $column) {
            // Fetch data for this column based on its id_attribute
            $columnData = DB::select('SELECT attribute_values FROM general_value_tables WHERE id_attr = ?', [$column->attribute_id]);

            // Organize the column data (assuming value column contains the actual data)
            $organizedData[$column->attribute_name] = array_map(function ($row) {
                return $row->attribute_values;  // Adjust according to the column data structure
            }, $columnData);
        }

        // Return the organized data with column names and data in the same format as the first case
        return [
            'success' => true,
            'columns' => array_keys($organizedData), // Column names
            'data' => $organizedData // Organized data for each column
        ];
    } 
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
private function addPrimaryKey($query)
{
    // Match the ALTER TABLE syntax to extract table name and attribute name
    if (preg_match('/ALTER\s+TABLE\s+(\w+)\s+ADD\s+PRIMARY\s+KEY\s*\(\s*(\w+)\s*\)/i', $query, $matches)) {
        $tableName = $matches[1];      // Extracted table name (e.g., T1)
        $attributeName = $matches[2];  // Extracted attribute name (e.g., attribut1)

        // Get the currently selected database from the session or context
        $dbName = session('selected_db'); // Ensure 'selected_db' is set in your session

        // Construct the internal SQL query for insertion
        $sql = "
            INSERT INTO General_PKEY_Tables (attribute_id, table_id, constraint_name, timestamp_insert)
            VALUES (
                (
                    SELECT ga.attribute_id
                    FROM General_ATTRIBUTE_Tables ga
                    JOIN General_TABLE_Tables gt ON ga.table_id = gt.table_id
                    JOIN General_BD_Tables gb ON gt.db_id = gb.id_bd
                    WHERE ga.attribute_name = ? AND gt.table_name = ? AND gb.db_name = ?
                ),
                (
                    SELECT gt.table_id
                    FROM General_TABLE_Tables gt
                    JOIN General_BD_Tables gb ON gt.db_id = gb.id_bd
                    WHERE gt.table_name = ? AND gb.db_name = ?
                ),
                CONCAT('PK_', ?, '_', ?),  -- Constraint name like 'PK_T1_attribute1'
                NOW()
            )
        ";

        // Return SQL and binding values
        return [
            'sql' => $sql,
            'bindings' => [
                $attributeName, $tableName, $dbName, // For attribute_id
                $tableName, $dbName,                 // For table_id
                $tableName, $attributeName           // For constraint_name
            ]
        ];
    }

    // Throw an exception if the query doesn't match the pattern
    throw new Exception('Invalid ALTER TABLE ADD PRIMARY KEY query.');
}
private function addForeignKey($userQuery)
{
    // Match the ALTER TABLE syntax to extract table name, constraint name, source attribute, target table, and target attribute
    if (preg_match('/ALTER\s+TABLE\s+(\w+)\s+ADD\s+CONSTRAINT\s+(\w+)\s+FOREIGN\s+KEY\s*\((\w+)\)\s+REFERENCES\s+(\w+)\s*\((\w+)\)/i', $userQuery, $matches)) {
        $sourceTable = $matches[1];       // Source table (e.g., T1)
        $constraintName = $matches[2];    // Constraint name (e.g., fk_example)
        $sourceAttribute = $matches[3];   // Source attribute (e.g., attribut1)
        $targetTable = $matches[4];       // Target table (e.g., T2)
        $targetAttribute = $matches[5];   // Target attribute (e.g., attribut2)

        // Retrieve the currently selected database
        $dbName = session('selected_db'); 
        if (!$dbName) {
            throw new \Exception('No database selected.');
        }

        // Log to ensure the database is selected correctly
        \Log::info("Database selected: " . $dbName);

        // Begin transaction to ensure atomicity
        DB::beginTransaction();
        try {
            // Construct the SQL query to insert the foreign key metadata
            $sql = "
                INSERT INTO General_FKEY_Tables (table_id, attribute_id, reference_table_id, reference_attribute_id, constraint_name)
                VALUES (
                    (SELECT gt.table_id FROM General_TABLE_Tables gt
                     JOIN General_BD_Tables gb ON gt.db_id = gb.id_bd
                     WHERE gt.table_name = ? AND gb.db_name = ?),
                     
                    (SELECT ga.attribute_id FROM General_ATTRIBUTE_Tables ga
                     JOIN General_TABLE_Tables gt ON ga.table_id = gt.table_id
                     JOIN General_BD_Tables gb ON gt.db_id = gb.id_bd
                     WHERE ga.attribute_name = ? AND gt.table_name = ? AND gb.db_name = ?),
                     
                    (SELECT gt.table_id FROM General_TABLE_Tables gt
                     JOIN General_BD_Tables gb ON gt.db_id = gb.id_bd
                     WHERE gt.table_name = ? AND gb.db_name = ?),
                     
                    (SELECT ga.attribute_id FROM General_ATTRIBUTE_Tables ga
                     JOIN General_TABLE_Tables gt ON ga.table_id = gt.table_id
                     JOIN General_BD_Tables gb ON gt.db_id = gb.id_bd
                     WHERE ga.attribute_name = ? AND gt.table_name = ? AND gb.db_name = ?),
                    
                    ?
                )
            ";

            // Log the SQL query for debugging purposes
            \Log::info("SQL query: " . $sql);

            // Bind the parameters in the correct order
            $bindings = [
                $sourceTable, $dbName,           // For source table ID
                $sourceAttribute, $sourceTable, $dbName,  // For source attribute ID
                $targetTable, $dbName,           // For target table ID
                $targetAttribute, $targetTable, $dbName,  // For target attribute ID
                $constraintName                  // For constraint name
            ];

            // Log the bindings to ensure they are correct
            \Log::info("SQL Bindings: " . json_encode($bindings));

            // Execute the query
            DB::insert($sql, $bindings);

            // Commit the transaction
            DB::commit();

            // Return success message
            return "Foreign key constraint '{$constraintName}' added successfully.";
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            \Log::error("Error adding foreign key: " . $e->getMessage());
            throw new \Exception("Failed to add foreign key: " . $e->getMessage());
        }
    }

    throw new \Exception('Invalid ALTER TABLE ADD FOREIGN KEY query.');
}
private function dropForeignKey($userQuery)
{
    // Match the ALTER TABLE syntax to extract table name and constraint name
    if (preg_match('/ALTER\s+TABLE\s+(\w+)\s+DROP\s+FOREIGN\s+KEY\s+(\w+)/i', $userQuery, $matches)) {
        $tableName = $matches[1];       // Table name (e.g., T1)
        $constraintName = $matches[2];  // Constraint name (e.g., fk_example)

        // Retrieve the currently selected database
        $dbName = session('selected_db'); 
        if (!$dbName) {
            throw new \Exception('No database selected.');
        }

        // Construct the SQL query to delete the foreign key constraint from the metadata table
        $sql = "
            DELETE FROM general_fkey_tables
            WHERE constraint_name = ?
            AND table_id = (
                SELECT table_id
                FROM general_table_tables gt
                JOIN general_bd_tables gb ON gt.db_id = gb.id_bd
                WHERE gt.table_name = ? AND gb.db_name = ?
            );
        ";

        // Execute the delete query
        DB::delete($sql, [$constraintName, $tableName, $dbName]);

        // Return success message
        return "Foreign key constraint '{$constraintName}' dropped successfully.";
    }

    throw new \Exception('Invalid ALTER TABLE DROP FOREIGN KEY query.');
}
// private function selectForeignKeyContent($query)
// {
//     $dbName = session('selected_db');
//     $dbRecord = DB::table('general_bd_tables')
//                   ->where('db_name', $dbName)
//                   ->first();

//     // Ensure a database has been selected
//     if (!$dbName) {
//         throw new Exception('No database selected. Use the "USE" command to select a database.');
//     }

//     // Extract the query for foreign key information
//     if (preg_match('/SELECT\s+\*\s+FROM\s+fkey/i', $query)) {

//         // Fetch foreign key information from 'general_fkey_tables'
//         $fkeyRecords = DB::select('SELECT fk.fkey_id, fk.constraint_name, fk.table_id, fk.reference_table_id, fk.attribute_id, fk.reference_attribute_id
//                                    FROM general_fkey_tables fk
//                                    INNER JOIN general_table_tables gt ON fk.table_id = gt.table_id
//                                    INNER JOIN general_attribute_tables ga ON fk.attribute_id = ga.attribute_id
//                                    WHERE gt.db_id = ?',
//                                    [$dbRecord->id_bd]);

//         if (empty($fkeyRecords)) {
//             throw new Exception('No foreign keys found in the selected database.');
//         }

//         // Initialize arrays for organizing the columns and data
//         $columns = [
//             'fk_name',
//             'table_name',
//             'attribute_name',
//             'reference_table_name',
//             'reference_attribute_name'
//         ];

//         $data = [];

//         // Iterate over foreign key records and organize data
//         foreach ($fkeyRecords as $fkey) {
//             // Fetch table names and attribute names using the respective IDs
//             $tableName = DB::table('general_table_tables')
//                 ->where('table_id', $fkey->table_id)
//                 ->value('table_name');

//             $attributeName = DB::table('general_attribute_tables')
//                 ->where('attribute_id', $fkey->attribute_id)
//                 ->value('attribute_name');

//             $referenceTableName = DB::table('general_table_tables')
//                 ->where('table_id', $fkey->reference_table_id)
//                 ->value('table_name');

//             $referenceAttributeName = DB::table('general_attribute_tables')
//                 ->where('attribute_id', $fkey->reference_attribute_id)
//                 ->value('attribute_name');

//             // Add the foreign key data to the result array
//             $data[] = [
//                 'fk_name' => $fkey->constraint_name,
//                 'table_name' => $tableName,
//                 'attribute_name' => $attributeName,
//                 'reference_table_name' => $referenceTableName,
//                 'reference_attribute_name' => $referenceAttributeName
//             ];
//         }

//         // Return the columns and organized data
//         return [
//             'columns' => $columns,
//             'data' => $data
//         ];
//     }

//     throw new Exception('Invalid query format for foreign key retrieval.');
// }

}


private function createTable($query)
    {
        // Step 1: Parse the CREATE TABLE query to extract table name and column definitions
        if (preg_match('/CREATE\s+TABLE\s+([a-zA-Z0-9_]+)\s*\((.+)\)/is', $query, $matches)) {
            $tableName = $matches[1];  // Extract the table name
            $columnsDefinition = $matches[2];  // Extract the column definitions
        
            // Step 2: Parse the columns (including data types and constraints)
            $columns = [];
            $columnPattern = '/([a-zA-Z0-9_]+)\s+([a-zA0-9_]+)(?:\s+PRIMARY\s+KEY)?(?:\s+FOREIGN\s+KEY\s+REFERENCES\s+([a-zA-Z0-9_]+)\s*\(([a-zA-Z0-9_]+)\))?/i';
            preg_match_all($columnPattern, $columnsDefinition, $columnMatches, PREG_SET_ORDER);
        
            foreach ($columnMatches as $columnMatch) {
                $columns[] = [
                    'column_name' => $columnMatch[1],
                    'data_type' => $columnMatch[2],
                    // 'is_primary_key' => isset($columnMatch[0]) && strpos($columnMatch[0], 'PRIMARY KEY') !== false,
                    // 'is_foreign_key' => isset($columnMatch[0]) && strpos($columnMatch[0], 'FOREIGN KEY') !== false,
                    'reference_table' => $columnMatch[3] ?? null,
                    'reference_column' => $columnMatch[4] ?? null,
                ];
            }
        
            // Step 3: Ensure the database is selected
            $dbName = session('selected_db');
            if (!$dbName) {
                throw new Exception('No database selected. Use the "USE" command to select a database.');
            }
        
            // Get the database ID
            $dbRecord = DB::table('General_BD_Tables')->where('db_name', $dbName)->first();
            if (!$dbRecord) {
                throw new Exception("Database '$dbName' does not exist.");
            }
            $dbId = $dbRecord->id_bd;
        
            // Step 4: Check if the table already exists by querying General_TABLE_Tables
            $existingTable = DB::table('General_TABLE_Tables')->where('db_id', $dbId)->where('table_name', $tableName)->first();
        
            // If the table exists, use the existing table_id
            if ($existingTable) {
                throw new Exception("Database '$dbName' does not exist.");

            } else {
                // Step 5: Prepare the SQL to insert the table into General_TABLE_Tables
                $tableInsertQuery = "INSERT INTO General_TABLE_Tables (db_id, table_name, timestamp_insert) VALUES (?, ?, NOW())";
                DB::insert($tableInsertQuery, [$dbId, $tableName]);
                $tableId = DB::getPdo()->lastInsertId();  // Get the last inserted table_id
            }
        
            // Prepare SQL queries and bindings
            $sqlQueries = [];
            $bindings = [];
        
            // Step 6: Combine the SQL for inserting columns into General_ATTRIBUTE_Tables and handling constraints
            foreach ($columns as $column) {
                // Step 6.1: Prepare column insert query, is_primary_key, is_foreign_key,
                $attributeInsertQuery = "INSERT INTO General_ATTRIBUTE_Tables (table_id, attribute_name, data_type ,timestamp_insert) VALUES (?, ?, ?, NOW())";
                $sqlQueries[] = $attributeInsertQuery;
                $bindings[] = [
                    $tableId, // Using the existing or newly inserted table_id
                    $column['column_name'],
                    $column['data_type'],
                    // $column['is_primary_key'] ? 1 : 0,
                    // $column['is_foreign_key'] ? 1 : 0
                ];
        
                // Step 6.2: Handle primary key constraint (if present)
                // if ($column['is_primary_key']) {
                //     $primaryKeyInsertQuery = "INSERT INTO General_PKEY_Tables (table_id, attribute_id, constraint_name, timestamp_insert) VALUES (?, ?, ?, NOW())";
                //     $sqlQueries[] = $primaryKeyInsertQuery;
                //     $bindings[] = [
                //         $tableId, // Using the existing or newly inserted table_id
                //         ':attribute_id', // Placeholder for actual attribute ID (we will replace it later)
                //         'PK_' . $tableName . '_' . $column['column_name']
                //     ];
                // }
        
                // Step 6.3: Handle foreign key constraint (if present)
                // if ($column['is_foreign_key']) {
                //     $referenceTableId = DB::table('General_TABLE_Tables')->where('table_name', $column['reference_table'])->value('table_id');
                //     $referenceAttributeId = DB::table('General_ATTRIBUTE_Tables')->where('table_id', $referenceTableId)->where('attribute_name', $column['reference_column'])->value('attribute_id');
        
                //     $foreignKeyInsertQuery = "INSERT INTO General_FKEY_Tables (table_id, attribute_id, reference_table_id, reference_attribute_id, constraint_name, timestamp_insert) VALUES (?, ?, ?, ?, ?, NOW())";
                //     $sqlQueries[] = $foreignKeyInsertQuery;
                //     $bindings[] = [
                //         $tableId, // Using the existing or newly inserted table_id
                //         ':attribute_id', // Placeholder for actual attribute ID (we will replace it later)
                //         $referenceTableId,
                //         $referenceAttributeId,
                //         'FK_' . $tableName . '_' . $column['column_name']
                //     ];
                // }
            }
        
            // Combine all queries into a single transaction
            return [
                'sql' => $sqlQueries, // Combined SQL queries
                'bindings' => $bindings  // Combined bindings for each query
            ];
        }
    
        throw new Exception('Invalid CREATE TABLE query.');
    }
}
function loadTables(dbId, dbName) {
    clearResults();
    // Step 1: Send the 'USE ${dbname}' query via AJAX to the controller
    $.ajax({
        url: '/run-query', // The route to the controller method that handles the query
        type: 'POST',
        data: {
            sql_query: 'USE ${dbName};', // The SQL query to use the selected database
            _token: $('meta[name="csrf-token"]').attr('content') // CSRF token for security
        },
        success: function(response) {
            // Handle the response from the 'USE' query (if needed)
            if (response.success) {
                console.log('Database changed to: ' + dbName);

                // Update the internal query output
                const internalQueryOutput = $('#internal-query-output');
                internalQueryOutput.empty(); // Clear previous content
                const bubble = $('<div class="query-bubble-string"></div>').text(SELECT id_bd FROM General_BD_Tables WHERE db_name = '${dbName}' ;);
                internalQueryOutput.append(bubble); // Add the new query bubble

                // Ensure the internal query section is visible
                internalQueryOutput.show();

            } else {
                alert('Failed to switch database: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Error while switching database: ' + error);
        }
    });

    // Step 2: Set the SQL query in the textarea for showing tables
    document.getElementById('sql-query').value = USE ${dbName};\nSHOW TABLES;;

    // Step 3: Remove 'selected-db' class from all database items
    document.querySelectorAll('.database-item').forEach(item => {
        item.classList.remove('selected-db');
    });

    // Step 4: Add 'selected-db' class to the clicked database item
    const clickedItem = event.currentTarget;
    clickedItem.classList.add('selected-db');

    // Step 5: Fetch tables from the server for the selected database
    $.get('/tables/' + dbId)
        .done(function(data) {
            const tableSelection = document.getElementById('table-selection');
            tableSelection.innerHTML = '';

            if (data.tables && data.tables.length > 0) {
                data.tables.forEach(function(table) {
                    const tableDiv = document.createElement('div');
                    tableDiv.classList.add('table-item');
                    tableDiv.innerText = table;

                    // Add click event listener to highlight the selected table
                    tableDiv.onclick = function () {
                        // Remove 'selected-table' class from all table items
                        document.querySelectorAll('.table-item').forEach(item => {
                            item.classList.remove('selected-table');
                        });

                        // Add 'selected-table' class to the clicked table item
                        this.classList.add('selected-table');

                        // Send AJAX request when tableDiv is clicked
                        $.ajax({
                            url: '/select', // Route to Laravel controller function 'select'
                            type: 'POST',
                            data: {
                                query: SELECT * FROM ${table},
                                _token: $('meta[name="csrf-token"]').attr('content') // CSRF token
                            },
                            success: function(response) {
                                
                                displayTableData(response); // Function to handle the response and display data

                                 // Update the internal query output
                                const internalQueryOutput = $('#internal-query-output');
                                internalQueryOutput.empty(); // Clear previous content
                                const bubble = $('<div class="query-bubble-string"></div>').text(`
                                SELECT
                                        attr.attribute_name ,
                                        val.attribute_values 
                                    FROM
                                        General_VALUE_Tables val
                                    JOIN
                                        General_ATTRIBUTE_Tables attr ON val.id_attr = attr.attribute_id
                                    JOIN
                                        General_TABLE_Tables tab ON attr.table_id = tab.table_id
                                    JOIN
                                        General_BD_Tables db ON tab.db_id = db.id_bd
                                    WHERE
                                        db.db_name = '${dbName}'
                                        AND tab.table_name = '${table}'
                                    GROUP BY val.value_id;`);
                                internalQueryOutput.append(bubble); // Add the new query bubble

                                // Ensure the internal query section is visible
                                internalQueryOutput.show();
                            },
                            error: function(xhr, status, error) {
                                console.error('Failed to fetch table data', status, error, xhr.responseText);
                                alert('Failed to fetch table data.');
                            }
                        });
                    };

                    tableSelection.appendChild(tableDiv);
                });
            } else {
                tableSelection.innerHTML = 'No tables found for this database.';
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX Request Failed', status, error, xhr.responseText);
            alert('Failed to load tables. Check the console for more details.');
        });
}

function loadTables(dbId, dbName) {
    clearResults();
    // Step 1: Send the 'USE ${dbname}' query via AJAX to the controller
    $.ajax({
        url: '/run-query', // The route to the controller method that handles the query
        type: 'POST',
        data: {
            sql_query: `USE ${dbName};`, // The SQL query to use the selected database
            _token: $('meta[name="csrf-token"]').attr('content') // CSRF token for security
        },
        success: function(response) {
            // Handle the response from the 'USE' query (if needed)
            if (response.success) {
                console.log('Database changed to: ' + dbName);
            } else {
                alert('Failed to switch database: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Error while switching database: ' + error);
        }
    });

    // Step 2: Set the SQL query in the textarea for showing tables
    document.getElementById('sql-query').value = `USE ${dbName};\nSHOW TABLES;`;

    // Step 3: Remove 'selected-db' class from all database items
    document.querySelectorAll('.database-item').forEach(item => {
        item.classList.remove('selected-db');
    });

    // Step 4: Add 'selected-db' class to the clicked database item
    const clickedItem = event.currentTarget;
    clickedItem.classList.add('selected-db');

    // Step 5: Fetch tables from the server for the selected database
    $.get('/tables/' + dbId)
        .done(function(data) {
            const tableSelection = document.getElementById('table-selection');
            tableSelection.innerHTML = '';

            if (data.tables && data.tables.length > 0) {
                data.tables.forEach(function(table) {
                    const tableDiv = document.createElement('div');
                    tableDiv.classList.add('table-item');
                    tableDiv.innerText = table;

                    // Add click event listener to highlight the selected table
                    tableDiv.onclick = function () {
                        // Remove 'selected-table' class from all table items
                        document.querySelectorAll('.table-item').forEach(item => {
                            item.classList.remove('selected-table');
                        });

                        // Add 'selected-table' class to the clicked table item
                        this.classList.add('selected-table');

                        // Send AJAX request when tableDiv is clicked
                        $.ajax({
                            url: '/select', // Route to Laravel controller function 'select'
                            type: 'POST',
                            data: {
                                query: `SELECT * FROM ${table}`,
                                _token: $('meta[name="csrf-token"]').attr('content') // CSRF token
                            },
                            success: function(response) {
                                displayTableData(response); // Function to handle the response and display data
                            },
                            error: function(xhr, status, error) {
                                console.error('Failed to fetch table data', status, error, xhr.responseText);
                                alert('Failed to fetch table data.');
                            }
                        });
                    };

                    tableSelection.appendChild(tableDiv);
                });
            } else {
                tableSelection.innerHTML = 'No tables found for this database.';
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX Request Failed', status, error, xhr.responseText);
            alert('Failed to load tables. Check the console for more details.');
        });
}
}
