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
        
        // Fetch the query history ordered by the timestamp_insert column
       
        // Return the view with the databases and query history
        return view('TP.index', compact('databases'));
    }

    // Fetch and display tables based on the clicked database
    public function getTables($db_id)
{
    try {
        // Fetch tables associated with the db_id from General_TABLE_TABLES and order by timestampinsert desc
        $tables = DB::table('General_TABLE_TABLES')
            ->where('db_id', $db_id)
            ->orderBy('timestamp_insert', 'desc')
            ->pluck('table_name'); // Assuming table_name is the column for table names

        // If no tables are found, return a helpful message
        if ($tables->isEmpty()) {
            return response()->json(['message' => 'No tables found for this database.'], 404);
        }

        // Return tables as a JSON response
        return response()->json(['tables' => $tables]);
    } catch (\Exception $e) {
        // Return a 500 error if something goes wrong
        return response()->json(['error' => 'Failed to fetch tables. ' . $e->getMessage()], 500);
    }
}
public function saveQuery(Request $request)
    {
        // Retrieve the query from the request
        $sqlQuery = $request->input('sql_query');
        
        // Insert the query into General_QUERY_table with the timestamp automatically set
        DB::table('General_QUERY_table')->insert([
            'content_query' => $sqlQuery,  // Insert the SQL query into the content_query column
            // timestamp_insert will automatically be filled with the current timestamp due to the default value
        ]);
        
        // Return a response indicating the query was saved successfully
        return response()->json(['message' => 'Query saved successfully!']);
    }
    // Function to get the last 10 queries from the history ordered by timestamp
public function getQueryHistory()
{
    // Retrieve the last 10 queries ordered by the timestamp_insert column
    $queryHistory = DB::table('General_QUERY_table')
        ->orderBy('timestamp_insert', 'desc') // Order by the timestamp of query execution
        ->limit(10)  // Limit to 10 most recent queries
        ->get();
        
    // Return the queries as a JSON response
    return response()->json(['queryHistory' => $queryHistory]);
}

}

