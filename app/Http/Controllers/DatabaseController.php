<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use DB;

class DatabaseController extends Controller
{
    // Fetch and display all databases
    public function index()
    {
        // Fetch database names from General_BD_TABLES
        $databases = DB::table('General_BD_TABLES')->pluck('db_name', 'id_bd');
        
        // Return the view and pass the databases
        return view('TP.index', compact('databases'));
    }

    // Fetch and display tables based on the clicked database
    public function getTables($db_id)
    {
        try {
            $tables = DB::table('General_TABLE_TABLES')
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

    // Execute SQL Query and save to history
    public function runQuery(Request $request)
    {
        $sqlQuery = $request->input('sql_query');

        try {
            // Execute the query
            $result = DB::select(DB::raw($sqlQuery));

            // Save the query in the General_QUERY_table
            DB::table('General_QUERY_table')->insert([
                'content_query' => $sqlQuery,
                // timestamp_insert is handled automatically by the database
            ]);

            // Return the query result
            return response()->json([
                'success' => true,
                'message' => 'Query executed and saved successfully.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Query execution failed: ' . $e->getMessage()
            ]);
        }

        
    }

    // Fetch query history
    public function getQueryHistory()
    {
        try {
            // Fetch query history sorted by timestamp_insert
            $history = DB::table('General_QUERY_table')
                ->orderBy('timestamp_insert', 'desc')
                ->get();

            return response()->json(['history' => $history]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch query history. ' . $e->getMessage()
            ], 500);
        }
    }
  





}
