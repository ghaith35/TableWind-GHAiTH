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


    /////////////////////////////////////////////////////////////
    public function runQuery(Request $request)
{
    $userQuery = trim($request->input('sql_query'));
    $response = ['success' => false, 'message' => ''];

    try {
        // Detect the type of the query
        $queryType = $this->getQueryType($userQuery);

        // Generate the internal query based on the detected type
        $internalQuery = $this->generateInternalQuery($queryType, $userQuery);
        
        // Handle 'USE' queries
        if ($queryType == 'USE') {
            if (preg_match('/USE\s+([a-zA-Z0-9_]+)/i', $userQuery, $matches)) {
                $dbName = $matches[1];
            // You can return the selected database name along with other data
            return response()->json([
                'success' => true,
                'selected_db' => $dbName,
                // other data as needed
            ]);
        }else{
            $result = DB::statement($internalQuery['sql'], $internalQuery['bindings']);
        // }

        // Refresh the list of databases after operations
        $databases = DB::select('SELECT  db_name from GENERAL_BD_TABLES ');

        return response()->json([
            'success' => true,
            'message' => 'Query executed successfully.',
            'internal_query' => $internalQuery['sql'],
            'databases' => $databases,
            'result' => $result ?? null
        ]);
        }
        
        // // Handle database operations (e.g., DROP_DATABASE, CREATE_DATABASE)
        // if (strtoupper($queryType) === 'DROP_DATABASE') {
        //     DB::transaction(function () use ($internalQuery) {
        //         DB::statement($internalQuery['sql'], $internalQuery['bindings']);
        //     });
        // } else {
            // For other queries like INSERT, DELETE, etc., use DB::statement
        }      
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to execute query: ' . $e->getMessage()
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
    } elseif (preg_match('/^\s*UPDATE\s+SET\s+/i', $query)) {
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
//             $query5 = "DROP DATABASE IF EXISTS `$escapedDbName`";
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
private function showTables($query)
    {
        if (preg_match('/SHOW\s+TABLES\s)/i', $query )) {
            
            return [
                'sql' => 'SELECT * from GENERAL_BD_TABLES where db_name = ?',
                'bindings' => [$dbName]
            ];
        }
        throw new Exception('Invalid CREATE DATABASE query.');
    }
}
