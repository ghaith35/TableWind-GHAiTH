<?php

use Illuminate\Support\Facades\DB;

public function runSql(Request $request)
{
    $query = $request->input('query');
    $firstWord = strtoupper($request->input('firstWord'));

    try {
        // Determine the type of SQL query
        switch ($firstWord) {
            case 'CREATE':
                case 'CREATE':
                    if (preg_match('/CREATE DATABASE (\w+);/i', $sqlQuery, $matches)) {
                        $dbName = $matches[1];
                        DB::table('GENERAL_BD_TABLES')->insert([
                            'db_name' => $dbName,
                            'timestamp_insert' => now(),
                        ]);
                        return response()->json(['message' => "Database '$dbName' created successfully."]);
                    } elseif (preg_match('/CREATE TABLE (\w+) \((.*)\);/i', $sqlQuery, $matches)) {
                        $tableName = $matches[1];
                        $columns = $matches[2]; // Extract columns and types
        
                        // Assume DB is selected elsewhere or provide db_id in the request
                        $dbId = 1; // Replace with logic to get the current db_id
        
                        // Insert into GENERAL_TABLE_TABLES
                        DB::table('GENERAL_TABLE_TABLES')->insert([
                            'table_name' => $tableName,
                            'timestamp_insert' => now(),
                            'db_id' => $dbId,
                        ]);
        
                        // Insert into GENERAL_ATTRIBUTE_TABLES for each column
                        $attributes = explode(',', $columns);
                        foreach ($attributes as $attribute) {
                            preg_match('/(\w+)\s+(\w+(\(.*\))?)/', trim($attribute), $attrMatches);
                            $attributeName = $attrMatches[1];
                            $dataType = $attrMatches[2];
        
                            // Assuming table_id is fetched based on table_name
                            $tableId = DB::table('GENERAL_TABLE_TABLES')
                                        ->where('table_name', $tableName)
                                        ->where('db_id', $dbId)
                                        ->value('id_table');
        
                            DB::table('GENERAL_ATTRIBUTE_TABLES')->insert([
                                'id_table' => $tableId,
                                'attribute_name' => $attributeName,
                                'data_type' => $dataType,
                            ]);
                        }
        
                        return response()->json(['message' => "Table '$tableName' created successfully."]);
                    }
                    break;
            
            case 'DROP':
                // Handle DROP statement
                DB::statement($query);
                return response()->json(['message' => 'Table dropped successfully.']);
                
            case 'SHOW':
                // Handle SHOW statement
                $result = DB::select($query);
                return response()->json(['result' => $result]);
                
            case 'INSERT':
                // Handle INSERT statement
                DB::statement($query);
                return response()->json(['message' => 'Data inserted successfully.']);
                
            case 'DELETE':
                // Handle DELETE statement
                DB::statement($query);
                return response()->json(['message' => 'Data deleted successfully.']);
                
            case 'UPDATE':
                // Handle UPDATE statement
                DB::statement($query);
                return response()->json(['message' => 'Data updated successfully.']);
                
            case 'SELECT':
                // Handle SELECT statement
                $result = DB::select($query);
                return response()->json(['result' => $result]);
                
            case 'ALTER':
                // Handle ALTER statement
                DB::statement($query);
                return response()->json(['message' => 'Table modified successfully.']);
                
            default:
                return response()->json(['error' => 'Unsupported SQL statement.']);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => 'Query execution failed: ' . $e->getMessage()]);
    }
}
