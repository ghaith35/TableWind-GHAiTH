<?php 
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableController extends Controller
{
    
    public function getTableContent($id_table) {
    try {
        // Fetch attributes (columns) for the selected table from GENERAL_ATTRIBUTE_TABLES
        $attributes = DB::table('GENERAL_ATTRIBUTE_TABLES')
            ->where('id_table', $id_table)
            ->orderBy('attribute_order') // Optional: order by your logic
            ->get();

        // Fetch rows from GENERAL_VALUES_TABLES, ordered by timestamp_insert
        $values = DB::table('GENERAL_VALUES_TABLES')
            ->whereIn('attribute_id', $attributes->pluck('id_attribute')) // Only select rows where the attribute_id is in the list of attributes
            ->orderBy('timestamp_insert')
            ->get();

        // Structure the data to return to the frontend
        $structuredData = $this->formatTableData($attributes, $values);

        return response()->json([
            'attributes' => $attributes,
            'rows' => $structuredData
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

// Helper function to format the data for frontend
private function formatTableData($attributes, $values) {
    $data = [];
    
    // Loop through each row and map the attribute names to their values
    foreach ($values as $value) {
        $row = [];
        foreach ($attributes as $attribute) {
            // The value of each attribute in a row (e.g., column1 => value1, column2 => value2)
            $row[$attribute->attribute_name] = $value->{$attribute->attribute_name} ?? null;
        }
        $data[] = $row;
    }

    return $data;
}

}
