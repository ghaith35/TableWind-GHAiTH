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
    public function select(Request $request)
    {
        $query = $request->input('query');
        preg_match('/SELECT \* FROM (\w+)/i', $query, $matches);
    
        if (empty($matches[1])) {
            return response()->json(['success' => false, 'message' => 'Invalid table name in query.']);
        }
    
        $tableName = $matches[1];
        $dbName = session('selected_db');
        $dbRecord = DB::table('general_bd_tables')
        ->where('db_name', $dbName)
        ->first();  // Ensure you track selected database in session
    
        // Retrieve the table ID from metadata
        $tableRecord = DB::select('SELECT table_id FROM general_table_tables WHERE db_id = ? AND table_name = ?', [$dbRecord->id_bd, $tableName]);
    
        if (empty($tableRecord)) {
            return response()->json(['success' => false, 'message' => 'Table not found.']);
        }
    
        $tableId = $tableRecord[0]->table_id;
    
        // Fetch column metadata
        $columns = DB::select('SELECT attribute_id, attribute_name FROM general_attribute_tables WHERE table_id = ? ORDER BY attribute_id', [$tableId]);
    
        if (empty($columns)) {
            return response()->json(['success' => false, 'message' => 'No columns found for this table.']);
        }
    
        // Fetch data for each column and organize it
        $organizedData = [];
        foreach ($columns as $column) {
            $columnData = DB::select('SELECT attribute_values FROM general_value_tables WHERE id_attr = ?', [$column->attribute_id]);
            $organizedData[$column->attribute_name] = array_map(function ($row) {
                return $row->attribute_values;
            }, $columnData);
        }
    
        return response()->json([
            'success' => true,
            'columns' => array_keys($organizedData),
            'data' => $organizedData
        ]);
}
function bindSqlWithBindings($sql, $bindings) {
    foreach ($bindings as $binding) {
        // Check if the binding is a string and add quotes
        $value = is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
        // Replace the first occurrence of '?' with the value
        $sql = preg_replace('/\?/', $value, $sql, 1);
    }
    return $sql;
}


  // Output: UPDATE users SET name = 'John Doe' WHERE id = 123

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
                    $sql = 'SELECT id_bd FROM  General_BD_Tables WHERE db_name = ?;';
                    $bindings = [$dbName];

                // Replace '?' with binding values
                $finalQuery = vsprintf(str_replace('?', '%s', $sql), array_map(function($binding) {
                    return is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                }, $bindings));


                
                    return response()->json([
                        'success' => true,
                        'selected_db' => $dbName,
                        'result' => 'database selected' ,
                        'internal_query' => $finalQuery
                    ]);
                }
            }elseif ($queryType == 'MODIFY_DATABASE') {
                $dbName = session('selected_db');
                    $alterResult = $this->modifyDatabase($userQuery);
                        session(['selected_db' => $dbName]);
                        DB::update($alterResult['sql'], $alterResult['bindings']);

                        $sql = $alterResult['sql'];
                        $bindings = $alterResult['bindings'];
                    // Replace '?' with binding values
                    $finalQuery = vsprintf(str_replace('?', '%s', $sql), array_map(function($binding) {
                        return is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                    }, $bindings));
    
    
                    
                        return response()->json([
                            'success' => true,
                            'selected_db' => $dbName,
                            'result' => 'database modified succesfully' ,
                            'internal_query' => $finalQuery
                        ]);
                    }

            elseif ($queryType == 'MODIFY_TABLE') {
                $dbName = session('selected_db');
                if (!$dbName) {
                    throw new \Exception('No database selected.');
                }
    
                $alterResult = $this->modifyTable($userQuery);
                DB::update($alterResult['sql'], $alterResult['bindings']);
                $sql = $alterResult['sql'];
                $bindings = $alterResult['bindings'];

                // Replace '?' with binding values
                $finalQuery = vsprintf(str_replace('?', '%s', $sql), array_map(function($binding) {
                    return is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                }, $bindings));


                return response()->json([
                    'success' => true,
                    'message' => 'Table modified successfully.',
                    'result' => 'Table modified successfully' ,
                    'internal_query' => $finalQuery
                ]);
            }elseif ($queryType == 'CREATE_DATABASE') {    
                
                $alterResult = $this->createDatabase($userQuery);
                DB::update($alterResult['sql'], $alterResult['bindings']);
                $sql = $alterResult['sql'];
                $bindings = $alterResult['bindings'];

                // Replace '?' with binding values
                $finalQuery = vsprintf(str_replace('?', '%s', $sql), array_map(function($binding) {
                    return is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                }, $bindings));


                return response()->json([
                    'success' => true,
                    'message' => 'Table modified successfully.',
                    'result' => 'database created successfully' ,
                    'internal_query' => $finalQuery
                ]);
            }elseif ($queryType == 'SELECT_VALUES') {
                try {
                    // Call the selectTableContent function to get the result
                    $result = $this->selectTableContent($userQuery);
            
                    // Bind the query with the parameters (this will not execute the query, just bind the values)
                    $sqlWithBindings = vsprintf(str_replace('?', '%s', $result['internal_query']), $result['bindings']);
            $tablename=$result['tablename']; 
                    // Return the prepared query as a JSON response
                    return response()->json([
                        'success' => true,
                        'columns' => $result['columns'],
                        'data' => $result['data'],
                        'internal_query' => $sqlWithBindings,  // Return the internal query with the values bound
                        'tablename'=>$tablename,
                    ]);
                } catch (\Exception $e) {
                    // Handle any exceptions
                    return response()->json([
                        'success' => false,
                        'message' => 'Error: ' . $e->getMessage(),
                    ], 500);
                }
            }
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
                $sql = $deleteResult['sql'];
                $bindings = $deleteResult['bindings'];
                $finalQuery = [];
                foreach ($sql as $index => $query) {
                    // Get the bindings for this query
                    $currentBindings = $bindings[$index];
                
                    // Replace placeholders with the corresponding binding values
                    $finalQuery[] = vsprintf(str_replace('?', '%s', $query), array_map(function($binding) {
                        // Handle string bindings with addslashes
                        return is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                    }, $currentBindings));
                }
                return response()->json([
                    'success' => true,
                    'message' => 'Data deleted successfully.',
                    'result' => 'data deleted  successfully' ,
                    'internal_query' => $finalQuery
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
                    
                  
                    $finalQueries = [];
                        foreach ($dropResult['sql'] as $index => $sql) {
                            $binding = $dropResult['bindings'][$index][0]; // Extract the single binding value
                            // Replace the named placeholder with the actual binding value
                            $finalQueries[] = str_replace(':dbname', "'" . addslashes($binding) . "'", $sql);
                        }
                    return response()->json([
                        'success' => true,
                        'message' => 'database dropped successfully.',
                        'result' => 'database dropped successfully' ,
                        'internal_query' => $finalQueries
                    ]);
                } catch (\Exception $e) {
                    // Rollback the transaction if any query fails
                    DB::rollBack();
                    throw new Exception('Transaction failed: ' . $e->getMessage());
                }
            }elseif ($queryType == 'CREATE_TABLE') {
                $createResult = $this->createTable($userQuery);
                
                try {
                    // Start a transaction for the first query
                    DB::beginTransaction();
            
                    // Execute the first SQL query with its binding
                    DB::statement($createResult['sql'][0], $createResult['bindings'][0]);
                    
                    // Commit the transaction for the first query
                    DB::commit();
                    // $bindings=$createResult['bindings'];
                    // $TableId = DB::table('General_TABLE_Tables')->max('table_id') ;
                    // foreach ($bindings as &$binding) {
                    //     $binding[0] = $TableId;  // Update the table_id (which is the first element in each array)
                    // }
                    // Begin a new transaction for the remaining queries
                    DB::beginTransaction();
                    
                    // Execute the remaining queries
                    for ($i = 1; $i < count($createResult['sql']); $i++) {
                        DB::statement($createResult['sql'][$i], $createResult['bindings'][$i]);
                    }
            
                    // Commit the transaction for the remaining queries
                    DB::commit();
            
                    // Prepare the final query output with placeholders replaced by actual values
                    $finalQueries = [];
                    foreach ($createResult['sql'] as $index => $sql) {
                        $bindings = $createResult['bindings'][$index];
                        foreach ($bindings as $key => $value) {
                            $sql = preg_replace('/\?/', is_numeric($value) ? $value : "'" . addslashes($value) . "'", $sql, 1);
                        }
                        $finalQueries[] = $sql;
                    }
            
                    return response()->json([
                        'success' => true,
                        'message' => 'Table created successfully.',
                        'result' => 'Table created successfully.',
                        'internal_query' => $finalQueries  // Return the final queries with bindings replaced
                    ]);
            
                } catch (\Exception $e) {
                    // Rollback the transaction if any query fails
                    DB::rollBack();
                    throw new Exception('Transaction failed: ' . $e->getMessage());
                }
            }
            
            elseif ($queryType == 'DROP_TABLE') {
                $dropResult = $this->dropTable($userQuery);
            
                // Start a database transaction
                DB::beginTransaction();

                try {
                    $TableId = DB::table('General_TABLE_Tables')->max('table_id') ; // Make sure to set TableId to the next auto increment value

                    // Execute DROP queries
                    foreach ($dropResult['sql'] as $index => $sql) {
                        DB::statement($sql, $dropResult['bindings'][$index]);
                    }

                    // Set AUTO_INCREMENT value for the table
                     // Use DB::statement instead of DB::insert

                    // Commit the transaction
                    DB::commit();
                    $query = "ALTER TABLE General_TABLE_Tables AUTO_INCREMENT = $TableId;";
                    DB::statement($query);
                    // Prepare the final query output with placeholders replaced by actual values
                    $finalQueries = [];
                    foreach ($dropResult['sql'] as $index => $sql) {
                        $bindings = $dropResult['bindings'][$index];
                        // Ensure bindings are treated as an array (if a single value, wrap it in an array)
                        if (isset($bindings[0])) {
                            $bindings = $bindings[0];
                        }
            
                        // Replace the placeholders with the binding values
                        $sql = str_replace('?', "'" . addslashes($bindings) . "'", $sql);
                        $finalQueries[] = $sql;
                    }
            
                    return response()->json([
                        'success' => true,
                        'message' => 'Table dropped successfully.',
                        'result' => 'Table dropped successfully.',
                        'internal_query' => $finalQueries  // Return the final queries
                    ]);
                } catch (\Exception $e) {
                    // Rollback the transaction if any query fails
                    DB::rollBack();
                    throw new Exception('Transaction failed: ' . $e->getMessage());
                }
            }
            
            elseif ($queryType == 'INSERT_VALUES') {
                try {
                    // Call the function to get the SQL query and bindings
                    $insertResult = $this->insertDataIntoTable($userQuery);
            
                    // Start a database transaction
                    DB::beginTransaction();
            
                    // Execute the SQL query once with all bindings
                    foreach ($insertResult['sql'] as $index => $sql) {
                        // Execute the query with its bindings
                        DB::statement($sql, $insertResult['bindings'][$index]);
                    }
            
                    // Commit the transaction
                    DB::commit();
            
                    return response()->json([
                        'success' => true,
                        'result' => 'Data inserted successfully.',
                        'internal_query' => $insertResult['final_sql']  // Return the full SQL with bindings
                    ]);
                } catch (\Exception $e) {
                    // Rollback the transaction if any query fails
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Error: ' . $e->getMessage(),
                    ], 500);
                }
            }
            
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
            }elseif ($queryType == 'DROP_PRIMARY_KEY') {
                // Handle DROP PRIMARY KEY queries
                $message = $this->dropPrimaryKey($userQuery);
                DB::beginTransaction();
            
                try {
                    // Extract SQL and bindings
                    $sql = $message['sql'];       // Single SQL query as a string
                    $bindings = $message['bindings']; // Array of binding values
            
                    // Execute the query with the bindings
                    DB::statement($sql, $bindings);
            
                    // Replace placeholders with binding values for display
                    $finalQuery = vsprintf(
                        str_replace('?', '%s', $sql),
                        array_map(function($binding) {
                            return is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                        }, $bindings)
                    );
            
                    // Commit the transaction
                    DB::commit();
            
                    // Return success response with internal query
                    return response()->json([
                        'success' => true,
                        'message' => 'Primary key added successfully.',
                        'result' => 'Primary key dropped successfully.',
                        'internal_query' => $finalQuery  // Display formatted query
                    ]);
            
                } catch (\Exception $e) {
                    // Rollback the transaction if any query fails
                    DB::rollBack();
                    throw new Exception('Transaction failed: ' . $e->getMessage());
                }
            
            }elseif ($queryType == 'ADD_FOREIGN_KEY') {
                $resultMessage = $this->addForeignKey($userQuery);
                DB::beginTransaction();
            
                try {
                    // Extract SQL and bindings
                    $sql = $resultMessage['sql'];       // Single SQL query as a string
                    $bindings = $resultMessage['bindings']; // Array of binding values
            
                    // Execute the query with the bindings
                    DB::statement($sql, $bindings);
            
                    // Replace placeholders with binding values for display
                    $finalQuery = vsprintf(
                        str_replace('?', '%s', $sql),
                        array_map(function($binding) {
                            return is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                        }, $bindings)
                    );
            
                    // Commit the transaction
                    DB::commit();
            
                    // Return success response with internal query
                    return response()->json([
                        'success' => true,
                        'message' => 'Primary key added successfully.',
                        'result' => 'Foreiegn key added successfully.',
                        'internal_query' => $finalQuery  // Display formatted query
                    ]);
            
                } catch (\Exception $e) {
                    // Rollback the transaction if any query fails
                    DB::rollBack();
                    throw new Exception('Transaction failed: ' . $e->getMessage());
                }
            }
            elseif ($queryType == 'DROP_FOREIGN_KEY') {
                // Call the dropForeignKey function with the matched query
                $message= $this->dropForeignKey($userQuery);
                DB::beginTransaction();
            
                try {
                    // Extract SQL and bindings
                    $sql = $message['sql'];       // Single SQL query as a string
                    $bindings = $message['bindings']; // Array of binding values
            
                    // Execute the query with the bindings
                    DB::statement($sql, $bindings);
            
                    // Replace placeholders with binding values for display
                    $finalQuery = vsprintf(
                        str_replace('?', '%s', $sql),
                        array_map(function($binding) {
                            return is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                        }, $bindings)
                    );
            
                    // Commit the transaction
                    DB::commit();
            
                    // Return success response with internal query
                    return response()->json([
                        'success' => true,
                        'message' => 'Primary key added successfully.',
                        'result' => 'Foreiegn key dropped successfully.',
                        'internal_query' => $finalQuery  // Display formatted query
                    ]);
            
                } catch (\Exception $e) {
                    // Rollback the transaction if any query fails
                    DB::rollBack();
                    throw new Exception('Transaction failed: ' . $e->getMessage());
                }
            }elseif ($queryType == 'ADD_COLUMN') {
                try {
                    // Call the function to get the SQL queries and bindings
                    $addColumnResult = $this->addColumnToTable($userQuery);
            
                    // Start a database transaction
                    DB::beginTransaction();
            
                    // Prepare the list of SQL queries that were executed
                    $finalQueries = [];
            
                    // Execute each SQL query individually
                    foreach ($addColumnResult['sql'] as $index => $sql) {
                        // Execute the query with bindings if provided
                        if (isset($addColumnResult['bindings'][$index])) {
                            DB::statement($sql, $addColumnResult['bindings'][$index]);
                        } else {
                            DB::statement($sql); // Execute without bindings if none provided
                        }
            
                        // Generate the full query by replacing ? with actual bindings
                        $bindings = $addColumnResult['bindings'][$index] ?? [];
                        foreach ($bindings as $binding) {
                            // If the binding is a string or number, replace with the value
                            if (is_string($binding)) {
                                // Escape string bindings properly (use addslashes for safety)
                                $binding = "'" . addslashes($binding) . "'";
                            } elseif (is_numeric($binding)) {
                                // Numeric bindings do not need escaping
                                $binding = $binding;
                            } elseif ($binding instanceof \DateTime) {
                                // If the binding is a DateTime, format it as a string
                                $binding = "'" . $binding->format('Y-m-d H:i:s') . "'";
                            }
            
                            // Replace the first occurrence of ? in the query with the binding
                            $sql = preg_replace('/\?/', $binding, $sql, 1);
                        }
            
                        // Store the final formatted query
                        $finalQueries[] = $sql;
                    }
            
                    // Commit the transaction
                    DB::commit();
            
                    // Return the response with the executed queries
                    return response()->json([
                        'success' => true,
                        'result' => 'Column added successfully.',
                        'internal_query' => $finalQueries  // Return the full queries with bindings
                    ]);
                } catch (\Exception $e) {
                    // Rollback the transaction in case of error
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Error: ' . $e->getMessage(),
                    ], 500);
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
                    $sql = $createResult['sql'];
                    $bindings = $createResult['bindings'];
                    $finalQuery = [];
                    foreach ($sql as $index => $query) {
                        // Get the bindings for this query
                        $currentBindings = $bindings[$index];
                    
                        // Replace placeholders with the corresponding binding values
                        $finalQuery[] = vsprintf(str_replace('?', '%s', $query), array_map(function($binding) {
                            // Handle string bindings with addslashes
                            return is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                        }, $currentBindings));
                    }
                    return response()->json([
                        'success' => true,
                        'message' => 'Data deleted successfully.',
                        'result' => 'column modified successfully' ,
                        'internal_query' => $finalQuery]);
                } catch (\Exception $e) {
                    // Rollback the transaction if any query fails
                    DB::rollBack();
                    throw new Exception('Transaction failed: ' . $e->getMessage());
                }
            }elseif ($queryType == 'DROP_COLUMN') {
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
                    $sql = $dropResult['sql'];
                    $bindings = $dropResult['bindings'];
                    $finalQuery = [];
                    foreach ($sql as $index => $query) {
                        // Get the bindings for this query
                        $currentBindings = $bindings[$index];
                    
                        // Replace placeholders with the corresponding binding values
                        $finalQuery[] = vsprintf(str_replace('?', '%s', $query), array_map(function($binding) {
                            // Handle string bindings with addslashes
                            return is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                        }, $currentBindings));
                    }
                    return response()->json([
                        'success' => true,
                        'message' => 'Data deleted successfully.',
                        'result' => 'column dropped successfully' ,
                        'internal_query' => $finalQuery]);
                } catch (\Exception $e) {
                    // Rollback the transaction if any query fails
                    DB::rollBack();
                    throw new Exception('Transaction failed: ' . $e->getMessage());
                }
            }elseif ($queryType == 'ADD_PRIMARY_KEY') {
                $addResult = $this->addPrimaryKey($userQuery);
            
                // Start a database transaction
                DB::beginTransaction();
            
                try {
                    // Extract SQL and bindings
                    $sql = $addResult['sql'];       // Single SQL query as a string
                    $bindings = $addResult['bindings']; // Array of binding values
            
                    // Execute the query with the bindings
                    DB::statement($sql, $bindings);
            
                    // Replace placeholders with binding values for display
                    $finalQuery = vsprintf(
                        str_replace('?', '%s', $sql),
                        array_map(function($binding) {
                            return is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                        }, $bindings)
                    );
            
                    // Commit the transaction
                    DB::commit();
            
                    // Return success response with internal query
                    return response()->json([
                        'success' => true,
                        'message' => 'Primary key added successfully.',
                        'result' => 'Primary key added successfully.',
                        'internal_query' => $finalQuery  // Display formatted query
                    ]);
            
                } catch (\Exception $e) {
                    // Rollback the transaction if any query fails
                    DB::rollBack();
                    throw new Exception('Transaction failed: ' . $e->getMessage());
                }
            }elseif ($queryType == 'MODIFY_COLUMN_NAME') {
                $dbName = session('selected_db');
                if (!$dbName) {
                    throw new \Exception('No database selected.');
                }
            
                $alterResult = $this->modifyColumnName($userQuery);
            
                // Bind the query and values directly
                $internalQuery = $alterResult['sql']; // The SQL query with placeholders
                $bindings = $alterResult['bindings']; // The values to be bound to the query
            
                // Use the bindings to replace placeholders in the SQL query for debugging purposes
                foreach ($bindings as $binding) {
                    $internalQuery = preg_replace('/\?/', "'$binding'", $internalQuery, 1);
                }
            
                // Execute the update query
                DB::update($alterResult['sql'], $alterResult['bindings']);
            
                // Return the internal query with values and the bindings to the JSON response
                return response()->json([
                    'success' => true,
                    'result' => 'Attribute name modified successfully.',
                    'internal_query' => $internalQuery, // Return the query with bound values
                ]);
            }elseif ($queryType == 'SHOW_TABLES') {
                $dbName = session('selected_db');
                if (!$dbName) {
                    throw new \Exception('No database selected.');
                }
            
                $alterResult = $this->showTables($userQuery);
            
                // Bind the query and values directly
                $internalQuery = $alterResult['sql']; // The SQL query with placeholders
                $bindings = $alterResult['bindings']; // The values to be bound to the query
                $dbname = $alterResult['dbname']; 
                $db_id=$alterResult['db_id']; 
                // Use the bindings to replace placeholders in the SQL query for debugging purposes
                foreach ($bindings as $binding) {
                    $internalQuery = preg_replace('/\?/', "'$binding'", $internalQuery, 1);
                }
            
                // Execute the update query
                //DB::update($alterResult['sql'], $alterResult['bindings']);
            
                // Return the internal query with values and the bindings to the JSON response
                return response()->json([
                    'success' => true,
                    'result' => 'tables showed successfully.',
                    'internal_query' => $internalQuery, // Return the query with bound values
                    'dbname'=>$dbname,
                    'db_id'=>$db_id,
                ]);
            }else{
                $internalQuery = $this->generateInternalQuery($queryType, $userQuery);
                $result = DB::statement($internalQuery['sql'], $internalQuery['bindings']);
    
                // Refresh databases or fetch new data depending on query type
                $databases = DB::select('SELECT db_name FROM general_bd_tables');
                return response()->json([
                    'success' => true,
                    'message' => 'Query executed successfully.',
                    'databases' => $databases,
                    'result' => $result ?? null,
                    'internal_query'=> 'hh
                    '
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
        } elseif (preg_match('/ALTER\s+TABLE\s+(\w+)\s+ADD\s+PRIMARY\s+KEY\s*\(\s*(\w+)\s*\)/i', $query)) {
            return 'ADD_PRIMARY_KEY';
        } elseif (preg_match('/ALTER\s+TABLE\s+(\w+)\s+ADD\s+CONSTRAINT\s+(\w+)\s+FOREIGN\s+KEY\s*\((\w+)\)\s+REFERENCES\s+(\w+)\s*\((\w+)\)/i', $query)) {
            return 'ADD_FOREIGN_KEY';
        } elseif (preg_match($pattern = '/^\s*ALTER\s+TABLE\s+(\w+)\s+DROP\s+PRIMARY\s+KEY\s+(\w+)\s*;?$/i', $query)) {
            return 'DROP_PRIMARY_KEY';
        } elseif (preg_match('/ALTER\s+TABLE\s+(\w+)\s+DROP\s+FOREIGN\s+KEY\s+(\w+);?/i', $query)) {
            return 'DROP_FOREIGN_KEY';
        }elseif (preg_match('/ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+RENAME\s+COLUMN\s+([a-zA-Z0-9_]+)\s+TO\s+([a-zA-Z0-9_]+)/i', $query)) {
            return 'MODIFY_COLUMN_NAME';
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
                    return $this->dropForeignKey($query);
                case 'SELECT_VALUES':
                    return $this->selectTableContent($query);
                case 'MODIFY_COLUMN_NAME':
                    return $this->modifyColumName($query);
                    case 'SHOW_TABLES':
                        return $this->showTables($query);
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
                'sql' => 'UPDATE General_TABLE_Tables t
                JOIN general_bd_tables b ON t.db_id = b.id_bd
                SET t.table_name = ?
                WHERE t.table_name = ? AND b.db_name = ?;',
                'bindings' => [$newTableName,$oldTableName,$dbName]
            ];
        }
        throw new Exception('Invalid ALTER DATABASE query.');
    }
    private function showTables($query)
    {
        // Match 'SHOW TABLES' query using a regular expression
        if (preg_match('/^\s*SHOW\s+TABLES\s*;?\s*$/i', $query)) {
            $dbName = session('selected_db');
            $dbRecord = DB::table('General_BD_Tables')->where('db_name', $dbName)->first();
            $db_id= $dbRecord->id_bd;
            return [
                'sql' => 'SELECT table_name FROM General_TABLE_Tables WHERE db_id  = (SELECT id_bd FROM General_BD_Tables WHERE db_name = ?);',
                'bindings' => [$dbName], // No bindings needed
                'dbname'=>$dbName,
                'db_id'=>$db_id
            ];
        }
        throw new Exception('Invalid SHOW TABLES query.');
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
                    // Delete from General_FKEY_Tables based on the database name
                    'DELETE FROM General_FKEY_Tables WHERE table_id IN (SELECT table_id FROM General_TABLE_Tables WHERE db_id = (SELECT id_bd FROM General_BD_Tables WHERE db_name = :dbname))',
                    
                    // Delete from General_ATTRIBUTE_Tables based on the database name
                    'DELETE FROM General_PKEY_Tables WHERE table_id IN (SELECT table_id FROM General_TABLE_Tables WHERE db_id = (SELECT id_bd FROM General_BD_Tables WHERE db_name = :dbname))',

                    // Delete from General_VALUE_Tables based on the attribute ID linked to tables within the given database
                    'DELETE FROM General_VALUE_Tables WHERE id_attr IN (SELECT attribute_id FROM General_ATTRIBUTE_Tables WHERE table_id IN (SELECT table_id FROM General_TABLE_Tables WHERE db_id = (SELECT id_bd FROM General_BD_Tables WHERE db_name = :dbname)))',
                    'DELETE FROM General_ATTRIBUTE_Tables WHERE table_id IN (SELECT table_id FROM General_TABLE_Tables WHERE db_id = (SELECT id_bd FROM General_BD_Tables WHERE db_name = :dbname))',

                    // Delete from General_TABLE_Tables based on the database name
                    'DELETE FROM General_TABLE_Tables WHERE db_id = (SELECT id_bd FROM General_BD_Tables WHERE db_name = :dbname)',
                    
                    // Finally, delete the database entry from General_BD_Tables
                    'DELETE FROM General_BD_Tables WHERE db_name = :dbname'
                ],
                
                'bindings' => [
                    [$dbName],
                    [$dbName],
                    [$dbName],
                    [$dbName],
                    [$dbName],
                    [$dbName]
                ]
            ];
        }
    }private function createTable($query)
    {
        // Step 1: Parse the CREATE TABLE query to extract table name and column definitions
        if (preg_match('/CREATE\s+TABLE\s+([a-zA-Z0-9_]+)\s*\((.+)\)/is', $query, $matches)) {
            $tableName = $matches[1];
            $columnsDefinition = $matches[2];
            
            // Step 2: Parse the columns
            $columns = [];
            $columnPattern = '/([a-zA-Z0-9_]+)\s+([a-zA-Z0-9_]+)(?:\s+(PRIMARY\s+KEY))?(?:\s+REFERENCES\s+([a-zA-Z0-9_]+)\s*\(([a-zA-Z0-9_]+)\))?/i';
            preg_match_all($columnPattern, $columnsDefinition, $columnMatches, PREG_SET_ORDER);
            
            foreach ($columnMatches as $columnMatch) {
                $columns[] = [
                    'column_name' => $columnMatch[1],
                    'data_type' => $columnMatch[2],
                    // 'is_primary_key' => !empty($columnMatch[3]),
                    // 'reference_table' => $columnMatch[4] ?? null,
                    // 'reference_column' => $columnMatch[5] ?? null,
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
            $existingTable = DB::table('General_TABLE_Tables')->where('db_id', $dbId)->where('table_name', $tableName)->first();
        
            // If the table exists, use the existing table_id
            if ($existingTable) {
                throw new Exception("table '$tableName' already exist.");

            } 
            // Prepare SQL and bindings arrays
            $sqlQueries = [];
            $bindings = [];
            $TableId = DB::table('General_TABLE_Tables')->max('table_id') + 1;

            // Step 4: Prepare table insertion query
            $tableInsertQuery = "INSERT INTO General_TABLE_Tables (db_id, table_name, timestamp_insert) VALUES ((select id_bd from General_BD_Tables where db_name = ?) , ?, NOW())";
            $sqlQueries[] = $tableInsertQuery;
            $bindings[] = [$dbName, $tableName];
    
            // Step 5: Prepare column insert queries and constraint handling
            foreach ($columns as $column) {
                $attributeInsertQuery = "INSERT INTO General_ATTRIBUTE_Tables (table_id, attribute_name, data_type, timestamp_insert) VALUES (?, ?, ?, NOW())";
                $sqlQueries[] = $attributeInsertQuery;
                $bindings[] = [
                    $TableId, // Placeholder to be replaced dynamically later
                    $column['column_name'],
                    $column['data_type']
                ];
            }
    
            // Step 6: Return the SQL and bindings arrays
            return [
                'sql' => $sqlQueries,
                'bindings' => $bindings,
            ];
        }
    
        throw new Exception('Invalid CREATE TABLE query.');
    }
    
    private function insertDataIntoTable($query)
    {
        // Step 1: Parse the INSERT INTO query
        if (preg_match('/INSERT\s+INTO\s+([a-zA-Z0-9_]+)\s*\(([^)]+)\)\s+VALUES\s*\(([^)]+)\)/i', $query, $matches)) {
            $tableName = $matches[1]; // Table name
            $columns = explode(',', str_replace(' ', '', $matches[2])); // Columns
            $values = explode(',', str_replace(' ', '', $matches[3])); // Values
    
            // Check if the number of columns matches the number of values
            if (count($columns) !== count($values)) {
                throw new Exception("Number of columns does not match number of values.");
            }
    
            // Step 2: Check if the database is selected
            $dbName = session('selected_db');
            if (!$dbName) {
                throw new Exception('No database selected. Please use the "USE" command to select a database.');
            }
    
            $dbRecord = DB::table('General_BD_Tables')->where('db_name', $dbName)->first();
            if (!$dbRecord) {
                throw new Exception("Database '$dbName' not found.");
            }
    
            // Step 3: Check if the table exists
            $tableRecord = DB::table('General_TABLE_Tables')->where('table_name', $tableName)->first();
            if (!$tableRecord) {
                throw new Exception("Table '$tableName' not found in database '$dbName'.");
            }
    
            // Retrieve the table ID
            $tableId = $tableRecord->table_id;
    
            // Step 4: Associate columns with their IDs from General_ATTRIBUTE_Tables
            $attributes = DB::table('General_ATTRIBUTE_Tables')->where('table_id', $tableId)->get()->keyBy('attribute_name');
            $dataToInsert = [];
            $bindings = [];
    
            // Loop through the columns and prepare data for insertion
            foreach ($columns as $index => $columnName) {
                if (!isset($attributes[$columnName])) {
                    throw new Exception("Column '$columnName' not found in table '$tableName'.");
                }
    
                $attributeId = $attributes[$columnName]->attribute_id;
                $dataToInsert[] = [
                    'id_attr' => $attributeId,
                    'attribute_values' => trim($values[$index], "'\""), // Remove quotes from values
                    'timestamp_insert' => now()
                ];
    
                // Prepare the bindings
                $bindings[] = [
                    $attributeId,
                    trim($values[$index], "'\""),  // Clean the values
                    now()  // Timestamp
                ];
            }
    
            // Step 5: Construct the SQL query
            $placeholders = implode(',', array_fill(0, count($bindings), '(?, ?, ?)')); // Placeholder for each row
            $sqlQuery = "INSERT INTO General_VALUE_Tables (id_attr, attribute_values, timestamp_insert) VALUES $placeholders";
    
            // Flatten the bindings array (because each value must be a separate binding)
            $flattenedBindings = array_merge(...$bindings);
    
            // Return the query, flattened bindings, and full SQL with values replaced
            $finalSql = $this->generateFinalSql($sqlQuery, $flattenedBindings);
    
            return [
                'sql' => [$sqlQuery],  // SQL query to insert values
                'bindings' => [$flattenedBindings],  // Flattened bindings for the query
                'final_sql' => $finalSql,  // Full SQL query with values replaced
                'success' => true,
                'message' => "Data successfully inserted into the table '$tableName'."
            ];
        }
    
        throw new Exception('Invalid INSERT INTO query.');
    }
    
    private function generateFinalSql($sqlQuery, $bindings)
    {
        // Replace each ? with the corresponding binding value
        foreach ($bindings as $binding) {
            // If the binding is a string or number, replace with the value
            if (is_string($binding)) {
                // Escape string bindings properly (use addslashes for safety)
                $binding = "'" . addslashes($binding) . "'";
            } elseif (is_numeric($binding)) {
                // Numeric bindings do not need escaping
                $binding = $binding;
            } elseif ($binding instanceof \DateTime) {
                // If the binding is a DateTime, format it as a string
                $binding = "'" . $binding->format('Y-m-d H:i:s') . "'";
            }
    
            // Replace the first occurrence of ? in the query with the binding
            $sqlQuery = preg_replace('/\?/', $binding, $sqlQuery, 1);
        }
    
        return $sqlQuery;  // Return the final SQL query with bindings replaced
    }
    private function selectTableContent($query)
{
    // Ensure a database has been selected
    $dbName = session('selected_db');
    $dbRecord = DB::table('general_bd_tables')
                  ->where('db_name', $dbName)
                  ->first();

    if (!$dbName) {
        throw new Exception('No database selected. Use the "USE" command to select a database.');
    }

    // Case 1: Handling foreign key query
    if (preg_match('/SELECT\s+\*\s+FROM\s+fkey/i', $query)) {
        $internalQuery = 'SELECT fk.fkey_id, fk.constraint_name, fk.table_id, fk.reference_table_id, fk.attribute_id, fk.reference_attribute_id
                          FROM general_fkey_tables fk
                          INNER JOIN general_table_tables gt ON fk.table_id = gt.table_id
                          INNER JOIN general_attribute_tables ga ON fk.attribute_id = ga.attribute_id
                          WHERE gt.db_id = (SELECT id_bd FROM general_bd_tables WHERE db_name = ?)';
        
        $bindings = [$dbRecord->db_name];

        $fkeyRecords = DB::select($internalQuery, $bindings);

        if (empty($fkeyRecords)) {
            throw new Exception('No foreign keys found in the selected database.');
        }

        $columns = ['fk_name', 'table_name', 'attribute_name', 'reference_table_name', 'reference_attribute_name'];
        $data = ['fk_name' => [], 'table_name' => [], 'attribute_name' => [], 'reference_table_name' => [], 'reference_attribute_name' => []];

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

            // Append data to the respective columns, ensuring each value is wrapped in an array
            $data['fk_name'][] = [$fkey->constraint_name];
            $data['table_name'][] = [$tableName];
            $data['attribute_name'][] = [$attributeName];
            $data['reference_table_name'][] = [$referenceTableName];
            $data['reference_attribute_name'][] = [$referenceAttributeName];
        }

        return [
            'success' => true,
            'internal_query' => $internalQuery,
            'bindings' => $bindings,
            'columns' => $columns,
            'data' => $data,
            'tablename'=>""
        ];
    }

    // Case 2: Handling primary key query
    elseif (preg_match('/SELECT\s+\*\s+FROM\s+pkey/i', $query)) {
        $internalQuery = 'SELECT pk.pkey_id, pk.constraint_name, pk.table_id, pk.attribute_id
                          FROM general_pkey_tables pk
                          INNER JOIN general_table_tables gt ON pk.table_id = gt.table_id
                          INNER JOIN general_attribute_tables ga ON pk.attribute_id = ga.attribute_id
                          WHERE gt.db_id = (SELECT id_bd FROM general_bd_tables WHERE db_name = ?)';
        
        $bindings = [$dbRecord->db_name];

        $pkeyRecords = DB::select($internalQuery, $bindings);

        if (empty($pkeyRecords)) {
            throw new Exception('No primary keys found in the selected database.');
        }

        $columns = ['pkey_name', 'table_name', 'attribute_name'];
        $data = ['pkey_name' => [], 'table_name' => [], 'attribute_name' => []];

        foreach ($pkeyRecords as $pkey) {
            $tableName = DB::table('general_table_tables')
                ->where('table_id', $pkey->table_id)
                ->value('table_name');

            $attributeName = DB::table('general_attribute_tables')
                ->where('attribute_id', $pkey->attribute_id)
                ->value('attribute_name');

            $data['pkey_name'][] = [$pkey->constraint_name];
            $data['table_name'][] = [$tableName];
            $data['attribute_name'][] = [$attributeName];
        }

        return [
            'success' => true,
            'internal_query' => $internalQuery,
            'bindings' => $bindings,
            'columns' => $columns,
            'data' => $data,
            'tablename'=>""
        ];
    }

    // Case 3: Regular SELECT query for table content
    elseif (preg_match('/SELECT\s+\*\s+FROM\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
        $tableName = $matches[1];

        // Query to retrieve table record to get the table ID
        $internalQuery = 'SELECT table_id FROM general_table_tables WHERE db_id = (SELECT id_bd FROM general_bd_tables WHERE db_name = ?) AND table_name = ?';
        $bindings = [$dbRecord->db_name, $tableName];

        // Execute the query to get the table ID
        $tableRecord = DB::select($internalQuery, $bindings);

        if (empty($tableRecord)) {
            throw new Exception('Table not found in the selected database.');
        }

        $tableId = $tableRecord[0]->table_id;

        // Query to fetch column metadata for the table and its values
        $internalQueryColumnsAndValues = 'SELECT ga.attribute_name, gv.attribute_values
                                          FROM general_attribute_tables ga
                                          INNER JOIN general_value_tables gv ON ga.attribute_id = gv.id_attr
                                          WHERE ga.table_id = (SELECT table_id FROM general_table_tables WHERE db_id = (SELECT id_bd FROM general_bd_tables WHERE db_name = ?) AND table_name = ?)';

        $bindingsColumnsAndValues = [$dbRecord->db_name, $tableName];

        // Fetch columns and values
        $columnData = DB::select($internalQueryColumnsAndValues, $bindingsColumnsAndValues);

        if (empty($columnData)) {
            throw new Exception('No columns or values found for this table.');
        }

        // Initialize the result array for organized data
        $organizedData = [];

        // Organize the column data into arrays
        foreach ($columnData as $column) {
            // Ensure the data for each column is wrapped in an array
            if (!isset($organizedData[$column->attribute_name])) {
                $organizedData[$column->attribute_name] = [];
            }
            $organizedData[$column->attribute_name][] = $column->attribute_values; // Wrap in array
        }

        // Ensure that each column value is an array, even if there's only one value
        foreach ($organizedData as $column => $values) {
            if (count($values) === 1) {
                $organizedData[$column] = [$values[0]];
            }
        }

        return [
            'success' => true,
            'internal_query' => $internalQueryColumnsAndValues,
            'bindings' => $bindingsColumnsAndValues,
            'columns' => array_keys($organizedData), // Column names
            'data' => $organizedData ,// Organized data for each column (values as arrays)
            'tablename'=>$tableName
        ];
    }

    // If no match for the query
    throw new Exception('Invalid SELECT query.');
}


private function dropTable($query)
{
    // Extract the table name from the DROP TABLE query
    if (preg_match('/DROP\s+TABLE\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
        $tableName = $matches[1];
        $dbName = session('selected_db');
        // Check if the table exists
        $tableExists = DB::table('General_TABLE_Tables')->where('table_name', $tableName)->exists();
        if (!$tableExists) {
            throw new Exception("Table '$tableName' does not exist.");
        }

        // Return the SQL queries and bindings with subqueries to get the table_id
        return [
            'sql' => [
                'DELETE FROM General_FKEY_Tables WHERE table_id = (SELECT table_id FROM General_TABLE_Tables WHERE table_name = ? and db_id =(SELECT id_bd FROM general_bd_tables WHERE db_name = ?))',
                'DELETE FROM General_PKEY_Tables WHERE table_id = (SELECT table_id FROM General_TABLE_Tables WHERE table_name = ? and db_id =(SELECT id_bd FROM general_bd_tables WHERE db_name = ?))',
                'DELETE FROM General_ATTRIBUTE_Tables WHERE table_id = (SELECT table_id FROM General_TABLE_Tables WHERE table_name = ? and db_id =(SELECT id_bd FROM general_bd_tables WHERE db_name = ?))',
                'DELETE FROM General_TABLE_Tables WHERE table_name = ? and db_id =(SELECT id_bd FROM general_bd_tables WHERE db_name = ?)'
            ],
            'bindings' => [
                [$tableName,$dbName], // Simple array with table name
                [$tableName,$dbName], // Simple array with table name
                [$tableName,$dbName], // Simple array with table name
                [$tableName,$dbName]  // Simple array with table name
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
                     WHERE gt.table_name = ? AND gb.db_name = ?     LIMIT 1
                    ),
                     
                    (SELECT ga.attribute_id FROM General_ATTRIBUTE_Tables ga
                     JOIN General_TABLE_Tables gt ON ga.table_id = gt.table_id
                     JOIN General_BD_Tables gb ON gt.db_id = gb.id_bd
                     WHERE ga.attribute_name = ? AND gt.table_name = ? AND gb.db_name = ?     LIMIT 1
                    ),
                     
                    (SELECT gt.table_id FROM General_TABLE_Tables gt
                     JOIN General_BD_Tables gb ON gt.db_id = gb.id_bd
                     WHERE gt.table_name = ? AND gb.db_name = ?     LIMIT 1
                    ),
                     
                    (SELECT ga.attribute_id FROM General_ATTRIBUTE_Tables ga
                     JOIN General_TABLE_Tables gt ON ga.table_id = gt.table_id
                     JOIN General_BD_Tables gb ON gt.db_id = gb.id_bd
                     WHERE ga.attribute_name = ? AND gt.table_name = ? AND gb.db_name = ?     LIMIT 1
                    ),
                    
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
            //DB::insert($sql, $bindings);

            // Commit the transaction
            DB::commit();

            // Return success message
             return [
                'sql' => $sql,
                'bindings' => $bindings       // For constraint_name
                
            ];
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
        $bindings   =[$constraintName, $tableName, $dbName];
        // Return success message
        return [
            'sql' => $sql,
            'bindings' => $bindings       // For constraint_name
            
        ];
    }

    throw new \Exception('Invalid ALTER TABLE DROP FOREIGN KEY query.');
}

private function modifyValue($query)
{
    // Extraire le nom de la table, les colonnes SET et les conditions WHERE
    if (preg_match('/UPDATE\s+([a-zA-Z0-9_]+)\s+SET\s+([^W]+)\s+WHERE\s+(.+)/i', $query, $matches)) {
        $tableName = $matches[1];
        $setClause = $matches[2];
        $whereClause = $matches[3];

        // Analyser la clause SET
        $setArray = [];
        $setPattern = '/([a-zA-Z0-9_]+)\s*=\s*([^,]+)/i';
        if (preg_match_all($setPattern, $setClause, $setMatches, PREG_SET_ORDER)) {
            foreach ($setMatches as $setMatch) {
                $setArray[trim($setMatch[1])] = trim($setMatch[2], "'");
            }
        }

        // Analyser la clause WHERE
        $whereArray = [];
        $wherePattern = '/([a-zA-Z0-9_]+)\s*=\s*([^,]+)/i';
        if (preg_match_all($wherePattern, $whereClause, $whereMatches, PREG_SET_ORDER)) {
            foreach ($whereMatches as $whereMatch) {
                $whereArray[trim($whereMatch[1])] = trim($whereMatch[2], "'");
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
                'message' => "Values updated successfully in table '$tableName'.",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw new Exception('Transaction failed: ' . $e->getMessage());
        }
    }

    throw new Exception('Invalid UPDATE query.');
}

private function deleteDataFromTable($query)
{
    // Extract the table name and the attribute values from the DELETE query
    if (preg_match('/DELETE\s+FROM\s+([a-zA-Z0-9_]+)\s+WHERE\s+([a-zA-Z0-9_]+)\s*=\s*([a-zA-Z0-9_]+)/i', $query, $matches)) {
        $tableName = $matches[1];
        $attributeName = $matches[2];
        $attributeValue = $matches[3];

        // Return the SQL queries and bindings
        return [
            'sql' => [
                'DELETE FROM General_VALUE_Tables WHERE id_attr = (SELECT attribute_id FROM General_ATTRIBUTE_Tables WHERE table_id = (SELECT table_id FROM General_TABLE_Tables WHERE table_name = ?)AND attribute_name = ?) AND attribute_values = ?',
            ],
            'bindings' => [
                [$tableName,$attributeName, $attributeValue],
                
            ]
        ];
    }

    throw new Exception('Invalid DELETE query.');
}private function addColumnToTable($query)
{
    // Extract the selected database name from the session
    $dbName = session('selected_db');
    if (!$dbName) {
        throw new Exception('No database selected.');
    }

    // Check if the database exists
    $dbRecord = DB::table('General_BD_Tables')
        ->where('db_name', $dbName)
        ->first();

    if (!$dbRecord) {
        throw new Exception("Database '$dbName' not found.");
    }

    // Extract table and column details from the query
    if (preg_match('/^\s*ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+ADD\s+COLUMN\s+([a-zA-Z0-9_]+)\s+([a-zA-Z0-9()]+(?:\([^\)]*\))?)\s*;?/i', $query, $matches)) {
        $tableName = $matches[1];
        $columnName = $matches[2];
        $dataType = $matches[3];

        // Check if the table exists
        $tableRecord = DB::table('General_TABLE_Tables')
            ->where('table_name', $tableName)
            ->where('db_id', $dbRecord->id_bd)
            ->first();

        if (!$tableRecord) {
            throw new Exception("Table '$tableName' does not exist in database '$dbName'.");
        }

        // Check if the column already exists in the table
        $columnExists = DB::table('General_ATTRIBUTE_Tables')
            ->where('table_id', $tableRecord->table_id)
            ->where('attribute_name', $columnName)
            ->exists();

        if ($columnExists) {
            throw new Exception("Column '$columnName' already exists in table '$tableName'.");
        }

        // Prepare the SQL query and bindings for inserting metadata
        $sqlQuery = "INSERT INTO General_ATTRIBUTE_Tables (table_id, attribute_name, data_type, timestamp_insert) VALUES (?, ?, ?, ?)";
        $bindings = [
            $tableRecord->table_id,  // table_id
            $columnName,             // attribute_name
            $dataType,               // data_type
            now()                    // timestamp_insert
        ];

        // Return the SQL query and bindings
        return [
            'sql' => [$sqlQuery],  // SQL query to insert metadata
            'bindings' => [$bindings]  // Correct bindings array
        ];
    }

    throw new Exception('Invalid ADD COLUMN query.');
}


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
            ->where('db_id', $dbRecord->id_bd)
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

        // Construire les requêtes SQL pour modifier la colonne , is_primary_key = ?, is_foreign_key = ?
        return [
            'sql' => [
                'UPDATE General_ATTRIBUTE_Tables SET data_type = ? WHERE attribute_name =? ;',
                'UPDATE General_VALUE_Tables SET timestamp_insert = ? WHERE id_attr = (select attribute_id from General_ATTRIBUTE_Tables where attribute_name=?);'
            ],
            'bindings' => [
                [
                    $newDataType,
                    // strpos(strtoupper($extraAttributes), 'PRIMARY KEY') !== false ? 1 : 0,
                    // strpos(strtoupper($extraAttributes), 'FOREIGN KEY') !== false ? 1 : 0,
                    $attributeName
                ],
                [
                    now(),
                    $attributeName
                ]
            ]
        ];
    }

    throw new Exception('Invalid MODIFY COLUMN query.');
}
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
            ->where('db_id', $dbRecord->id_bd)
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
                // Delete from General_ATTRIBUTE_Tables (using IN for db_id)
                'DELETE FROM General_ATTRIBUTE_Tables 
                 WHERE attribute_name = ? 
                 AND table_id IN (
                     SELECT table_id 
                     FROM General_TABLE_Tables 
                     WHERE db_id = (
                         SELECT id_bd 
                         FROM General_BD_Tables 
                         WHERE db_name = ?
                     )
                 );',
            
                // Delete from General_VALUE_Tables (handle multiple attribute_id with IN)
                'DELETE FROM General_VALUE_Tables 
                 WHERE id_attr IN (
                     SELECT attribute_id 
                     FROM General_ATTRIBUTE_Tables 
                     WHERE attribute_name = ?
                 );',
            
                // Delete from General_FKEY_Tables (handle multiple attribute_id with IN)
                'DELETE FROM General_FKEY_Tables 
                 WHERE attribute_id IN (
                     SELECT attribute_id 
                     FROM General_ATTRIBUTE_Tables 
                     WHERE attribute_name = ?
                 ) 
                 OR reference_attribute_id IN (
                     SELECT attribute_id 
                     FROM General_ATTRIBUTE_Tables 
                     WHERE attribute_name = ?
                 );',
            
                // Delete from General_PKEY_Tables (handle multiple attribute_id with IN)
                'DELETE FROM General_PKEY_Tables 
                 WHERE attribute_id IN (
                     SELECT attribute_id 
                     FROM General_ATTRIBUTE_Tables 
                     WHERE attribute_name = ?
                 );'
            ],
            'bindings' => [
                [$attributeName, $dbName],
                [$attributeName],
                [$attributeName, $attributeName],
                [$attributeName]
            ]
            
        ];
    }

    throw new Exception('Invalid DROP COLUMN query.');
}
private function dropPrimaryKey($query)
{
    // Match the ALTER TABLE syntax to extract the table name and primary key constraint name
    if (preg_match($pattern = '/^\s*ALTER\s+TABLE\s+(\w+)\s+DROP\s+PRIMARY\s+KEY\s+(\w+)\s*;?$/i', $query, $matches)) {
        $tableName = $matches[1];       // Extracted table name
        $constraintName = $matches[2];  // Extracted primary key constraint name

        // Get the currently selected database from the session or context
        $dbName = session('selected_db'); // Ensure 'selected_db' is set in your session

        // Construct the internal SQL query to remove the specific primary key constraint from metadata
        $sql = "
            DELETE FROM General_PKEY_Tables
            WHERE constraint_name = ? AND table_id = (
                SELECT table_id FROM General_TABLE_Tables gt
                JOIN General_BD_Tables gb ON gt.db_id = gb.id_bd
                WHERE gt.table_name = ? AND gb.db_name = ?
            )
        ";

        // Return SQL and binding values
        return [
            'sql' => $sql,
            'bindings' => [$constraintName, $tableName, $dbName]
        ];
    }

    // Throw an exception if the query doesn't match the pattern
    throw new Exception('Invalid ALTER TABLE DROP PRIMARY KEY query.');
}
private function modifyColumnName($query){

    // Récupérer le nom de la base de données sélectionnée
    $dbName = session('selected_db');
    $dbRecord = DB::table('General_BD_Tables')->where('db_name', $dbName)->first();

    if (!$dbRecord) {
        throw new Exception("Database '$dbName' does not exist.");
    }

    // Match the query to get table and column names
    if (preg_match('/ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+RENAME\s+COLUMN\s+([a-zA-Z0-9_]+)\s+TO\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
        $tableName = $matches[1];
        $attributeName = $matches[2];
        $newattributeName = $matches[3];

        // Vérifier si la table existe
        $tableRecord = DB::table('General_TABLE_Tables')
            ->where('table_name', $tableName)
            ->where('db_id', $dbRecord->id_bd)
            ->first();

        if (!$tableRecord) {
            throw new Exception("Table '$tableName' does not exist in the database '$dbName'.");
        }

        // Now check for the column using table name and attribute name
        $attributeRecord = DB::table('General_ATTRIBUTE_Tables')
            ->join('General_TABLE_Tables', 'General_ATTRIBUTE_Tables.table_id', '=', 'General_TABLE_Tables.table_id')
            ->where('General_TABLE_Tables.table_name', $tableName)
            ->where('General_ATTRIBUTE_Tables.attribute_name', $attributeName)
            ->first();

        if (!$attributeRecord) {
            throw new Exception("Column '$attributeName' does not exist in table '$tableName'.");
        }

        // Return the updated SQL query with the new condition based on table_name and attribute_name
        return [
            'sql' => 'UPDATE general_attribute_tables ga
                      INNER JOIN general_table_tables gt ON ga.table_id = gt.table_id
                      SET ga.attribute_name = ?
                      WHERE ga.attribute_name = ? AND gt.table_name = ? AND gt.db_id=(select id_bd from  General_BD_Tables where db_name = ?)',
            'bindings' => [$newattributeName, $attributeName, $tableName,$dbName]
        ];
    }

    throw new Exception('Invalid ALTER TABLE NAME query.');
}

}
