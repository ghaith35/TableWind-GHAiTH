<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Modern SQL Client Interface</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            display: flex;
            height: 100vh;
            color: #333;
            background-color: #f0f2f5;
        }

        h2,
        h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #007bff;
            border-bottom: 2px solid #ddd;
            padding-bottom: 5px;
        }

        .sidebar {
            width: 250px;
            background-color: #ffffff;
            padding: 20px;
            overflow-y: auto;
            border-right: 1px solid #ddd;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .database-item {
            /* padding: 12px;
            margin-bottom: 12px;
            border-radius: 8px;
            
            background-color: #f8f9fa;
            transition: background-color 0.3s, transform 0.3s; */
            display: inline-block;
    background-color: #f0f0f0;
    border: 1px solid #ccc;
    border-radius: 15px;
    padding: 10px 15px;
    margin: 5px;
    max-width: 90%;           /* Prevent bubbles from exceeding container width */
    word-wrap: break-word;    /* Wrap long queries */
    font-family: monospace;   /* For code-like appearance */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    color: #333;
    cursor: pointer;
    display: flex;           /* Use flexbox for horizontal layout */
    flex-wrap: wrap;         /* Allows items to wrap to the next line if necessary */
    gap: 10px;               /* Adds space between items */
    justify-content: flex-start;
        }

        .database-item:hover {
            background-color: #f1f8e9;
            transform: translateX(10px);
        }

        .table-item {
            /* padding: 12px;
            margin-bottom: 12px;
            border-radius: 8px;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.3s; */
            display: inline-block;
    background-color: #f0f0f0;
    border: 1px solid #ccc;
    border-radius: 15px;
    padding: 10px 15px;
    margin: 5px;
    max-width: 90%;           /* Prevent bubbles from exceeding container width */
    word-wrap: break-word;    /* Wrap long queries */
    font-family: monospace;   /* For code-like appearance */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    color: #333;
    cursor: pointer;
    display: flex;           /* Use flexbox for horizontal layout */
    flex-wrap: wrap;         /* Allows items to wrap to the next line if necessary */
    gap: 10px;               /* Adds space between items */
    justify-content: flex-start;
        }

        .table-item:hover {
            background-color: #f1f8e9;
            transform: translateX(10px);
        }

        .tables-section {
            flex-grow: 1;
            overflow-y: auto;
            margin-top: 20px;
        }

        .main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
            gap: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .query-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .query-toolbar button {
            padding: 10px 20px;
            margin-right: 10px;
            border: none;
            background-color: #007bff;
            color: #fff;
            cursor: pointer;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s, transform 0.3s;
        }

        .query-toolbar button:hover {
            background-color: #0056b3;
            transform: scale(1.05);
        }

        .middle {
            max-height: 315px;
            overflow-y: auto;
            margin-bottom: 20px;
            /* Optional for spacing */
        }

        .query-input {
            width: 100%;
            min-height: 100px;
            max-height: 300px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: inset 0 1px 5px rgba(0, 0, 0, 0.1);
            resize: vertical;
            padding: 10px;
        }

        .query-results {
            width: 100%;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            min-height: 250px;
        }

        .content {
            display: flex;
            gap: 20px;
            height: 70%;
        }

        .content-half {

            width:480px;
            flex: 1;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .query-log {
            background-color: #fff;
            border-left: 1px solid #ddd;
            box-shadow: -2px 0 15px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 20px;
            flex-shrink: 0;
            flex-grow: 0;
            width: 300px;
            overflow-y: auto;
        }

        .log-entry {
           display: inline-block;
    background-color: #f0f0f0;
    border: 1px solid #ccc;
    border-radius: 15px;
    padding: 10px 15px;
    margin: 5px;
    max-width: 90%;           /* Prevent bubbles from exceeding container width */
    word-wrap: break-word;    /* Wrap long queries */
    font-family: monospace;   /* For code-like appearance */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    color: #333;
    cursor: pointer;
    display: flex;           /* Use flexbox for horizontal layout */
    flex-wrap: wrap;         /* Allows items to wrap to the next line if necessary */
    gap: 20px;               /* Adds space between items */
    justify-content: flex-start;
        }

        .log-entry:hover {
            background-color: #e9ecef;
            transform: translateX(5px);
        }
        .selected-db {
    background-color: #f1f8e9; /* Green */
    
}
/* General table styling */
/* General table styling */
#result-output table {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0; /* Reduced margin for a more compact display */
    font-size: 14px; /* Smaller font size for a more compact look */
    text-align: left;
    background-color: #ffffff; /* White background for a clean look */
    border-radius: 6px; /* Slightly rounded corners for a softer look */
    overflow: hidden; /* Ensures rounded corners on tables with borders */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Subtle shadow for a lifted effect */
}

/* Table header styling */
#result-output table thead tr {
    background-color: #f1f8e9; /* Light green header for a modern look */
    color: #333; /* Dark text for contrast */
    word-wrap: break-word; /* Wrap long queries */
    font-family: monospace; /* Monospace font for code-like appearance */
    text-align: left;
}

/* Remove borders between columns and rows */
#result-output table th,
#result-output table td {
    padding: 10px 12px; /* Reduced padding for smaller cells */
    vertical-align: middle; /* Ensure proper alignment */
}

/* Alternate row background color */
#result-output table tbody tr:nth-child(even) {
    background-color: #f9f9f9; /* Light gray for alternate rows */
}

/* Hover effect for rows */
#result-output table tbody tr:hover {
    background-color: #e3f2fd; /* Light blue hover effect for rows */
    cursor: pointer;
}






/* Style for selected database indicator */


/* Style for selected table */
.selected-table {
    background-color: #f1f8e9; /* Green color */
}
#internal-query-output{
    width:100%;

}
#result-output, #internal-query-output {
    height: 120%;  /* Occupy the full height of the container */
    overflow-y: auto;  /* Make the content scrollable if it exceeds the height */
}



/* Optional: Limit the height of the content area to prevent overflow */
.query-results {
    max-height: 700px;  /* You can adjust this value to your preferred height */
    overflow-y: auto;   /* Enable scrolling if content exceeds this height */
}


/* Style for Array Queries */
.query-bubble-array {
    display: inline-block;
    background-color: #f1f8e9;
    border: 1px solid #ccc;
    border-radius: 15px;
    padding: 10px 15px;
    margin: 5px;
    max-width: 90%;           /* Prevent bubbles from exceeding container width */
    word-wrap: break-word;    /* Wrap long queries */
    font-family: monospace;   /* For code-like appearance */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    color: #333;
    display: flex;
    flex-wrap: wrap;          /* Ensure bubbles wrap to the next line */
    gap: 10px;
}

/* Style for String Query */
.query-bubble-string {
    background-color: #f1f8e9;
    border: 1px solid #ccc;
    border-radius: 15px;
    padding: 10px 15px;
    margin: 5px;
    max-width: 90%;           /* Prevent bubbles from exceeding container width */
    word-wrap: break-word;    /* Wrap long queries */
    font-family: monospace;   /* For code-like appearance */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    color: #333;
}
/* Style for the JSON output */
.json-result {
    background-color: #f1f8e9;
    border: 1px solid #ccc;
    border-radius: 15px;
    padding: 10px 15px;
    margin: 5px;
    max-width: 90%;           /* Prevent bubbles from exceeding container width */
    word-wrap: break-word;    /* Wrap long queries */
    font-family: monospace;   /* For code-like appearance */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    color: #333;}

</style>

    </style>
</head>

<body>

    <div class="sidebar">
        <h2>Databases</h2>
        <div class="middle">

            @foreach($databases as $dbId => $dbName)
                <div class="database-item" onclick="loadTables({{ $dbId }}, '{{ $dbName }}', event)"> {{ $dbName }}</div>
            @endforeach

        </div>
        <h3>Tables</h3>
        <div class="tables-section">

            <div id="table-selection"></div>
        </div>
    </div>

    <div class="main">
        <div class="query-section">
            <h2>SQL Query</h2>

            <div class="query-toolbar">
                <button onclick="runQuery()">Run Query</button>
                <button onclick="clearResults()">Clear</button>
            </div>
            <textarea id="sql-query" class="query-input" placeholder="Write your SQL query here..."></textarea>
        </div>

        <div class="content">
        <div class="content-half query-results">
    <h2>Query Result</h2>
    <div id="result-output">
        <!-- Le tableau sera généré ici -->
    </div>
</div>

<div class="content-half query-results">
    <h2>Internal Query</h2>
    <div id="internal-query-output">
        <!-- La requête interne sera affichée ici -->
    </div>
</div>


        </div>
    </div>
    <div class="query-log">
    <h2>Query History</h2>
    <div class="middle-query">
        @if(isset($queries) && count($queries) > 0)
                @foreach($queries as $query)
                <div class="log-entry" onclick="populateQuery(`{!! addslashes($query->content_query) !!}`, event)">{{ $query->content_query }}</div>
                @endforeach
        @else
            <p>No saved queries found.</p>
        @endif
    </div>
</div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Optional function to load table content (this could be implemented as needed)
        // Function to load tables for a given database
        function runQuery() {
    // Retrieve the SQL query from the textarea
    const sqlQuery = $('#sql-query').val();

    // Verify the input isn't empty
    if (sqlQuery.trim() === '') {
        alert('Please enter a valid SQL query.');
        return;
    }

    // Send the SQL query to the backend via AJAX
    $.ajax({
        url: '/run-query', // Laravel route URL
        type: 'POST',
        data: {
            sql_query: sqlQuery,
            _token: $('meta[name="csrf-token"]').attr('content') // CSRF token for security
        },
        success: function(response) {
            // Clear previous internal query bubbles
            $('#internal-query-output').empty();

            if (response.success) {
                if (response.selected_db) {
                    // Update UI to display selected database
                    $('#selected-db').html(`Selected Database: <strong>${response.selected_db}</strong>`);

                    // Highlight the selected database in the sidebar
                    highlightSelectedDb(response.selected_db);

                    // Optional: highlight the query or set focus to the query input after the USE command
                    $('#sql-query').val(`USE ${response.selected_db};`);
                }
                if (response.tablename) {
                    // Update UI to display selected database
                    $('#selected-table').html(` <strong>${response.tablename}</strong>`);

                    // Highlight the selected database in the sidebar
                    highlightSelectedtable(response.tablename);

                    // Optional: highlight the query or set focus to the query input after the USE command
                    // $('#sql-query').val(`USE ${response.tablename};`);
                }
                if (response.db_id) {
                    // Update UI to display selected database
                    loadTables2(response.db_id, response.dbname);
                }
                // Display internal queries as bubble elements
                const bubbleContainer = $('#internal-query-output');
                
                if (response.internal_query) {
                    // Check if internal_query is an array or a string and display accordingly
                    if (Array.isArray(response.internal_query)) {
                        response.internal_query.forEach(function(query) {
                            const bubble = $('<div class="query-bubble-array"></div>').text(query);
                            bubbleContainer.append(bubble);
                        });
                    } else {
                        // If it's a string, just display it as one bubble
                        const bubble = $('<div class="query-bubble-string"></div>').text(response.internal_query);
            bubbleContainer.append(bubble);
                    }
                }

                // Existing logic for handling table results or databases
                if (response.columns && response.data) {
                    let resultHtml = '<table border="1" cellpadding="10" cellspacing="0"><thead><tr>';
                    
                    // Create table headers from columns
                    response.columns.forEach(function(column) {
                        resultHtml += `<th>${column}</th>`;
                    });
                    resultHtml += '</tr></thead><tbody>';

                    // Create table rows from the data
                    let rowCount = response.data[response.columns[0]].length; // Get the number of rows
                    for (let i = 0; i < rowCount; i++) {
                        resultHtml += '<tr>';
                        response.columns.forEach(function(column) {
                            resultHtml += `<td>${response.data[column][i]}</td>`;
                        });
                        resultHtml += '</tr>';
                    }

                    resultHtml += '</tbody></table>';
                    $('#result-output').html(resultHtml);
                } else {
                    // If the result is not a table, just show the result as JSON
                    $('#result-output').html('<pre class="json-result">' + JSON.stringify(response.result, null, 2) + '</pre>');
                }
            } else {
                // Handle failure response
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Failed to execute query: ' + error);
        }
    });
}


function highlightSelectedDb(dbName) {
    // Remove 'selected-db' class from all database items
    document.querySelectorAll('.database-item').forEach(item => {
        item.classList.remove('selected-db');
    });

    // Loop through all database items to find a match
    document.querySelectorAll('.database-item').forEach(item => {
        if (item.textContent.trim() === dbName.trim()) {
            item.classList.add('selected-db');
        }
    });
}
function highlightSelectedtable(tablename) {
    // Remove 'selected-db' class from all database items
    document.querySelectorAll('.table-item').forEach(item => {
        item.classList.remove('selected-table');
    });

    // Loop through all database items to find a match
    document.querySelectorAll('.table-item').forEach(item => {
        if (item.textContent.trim() === tablename.trim()) {
            item.classList.add('selected-table');
        }
    });
}

function loadTables2(dbId, dbName) {
    // Clear previous results
    // clearResults();

    // // Update internal query output to show the SELECT and SHOW TABLES queries
    // const internalQueryOutput = $('#internal-query-output');
    // internalQueryOutput.empty(); // Clear previous content
    // const showTablesQueryBubble = $('<div class="query-bubble-string"></div>')
    //     .text(`SELECT table_name FROM General_TABLE_Tables WHERE db_id = ${dbId};`);
    // internalQueryOutput.append(showTablesQueryBubble);

    // Fetch tables for the selected database
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
                        // Highlight the selected table
                        document.querySelectorAll('.table-item').forEach(item => {
                            item.classList.remove('selected-table');
                        });
                        this.classList.add('selected-table');

                        // Fetch and display the table data
                        $.ajax({
                            url: '/select', // Your route for selecting the table
                            type: 'POST',
                            data: {
                                query: `SELECT * FROM ${table}`,
                                _token: $('meta[name="csrf-token"]').attr('content') // CSRF token
                            },
                            success: function(response) {
                                displayTableData(response); // Function to handle the response and display data

                                // Show internal query for the selected table
                                // internalQueryOutput.empty(); // Clear previous content
                                // const internalQueryBubble = $('<div class="query-bubble-string"></div>').text(`
                                //     SELECT * FROM ${table};
                                // `);
                                // internalQueryOutput.append(internalQueryBubble);
                                // internalQueryOutput.show();
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
    // clearResults();
    // Step 1: Send the 'USE ${dbname}' query via AJAX to the controller
    $.ajax({
        url: '/run-query', // The route to the controller method that handles the query
        type: 'POST',
        data: {
            sql_query: `USE ${dbName};`, // The SQL query to use the selected database
            _token: $('meta[name="csrf-token"]').attr('content') // CSRF token for security
        },
        success: function(response) {
                          // const bubbleContainer = $('#internal-query-output');

            // Handle the response from the 'USE' query (if needed)
            if (response.success) {
                console.log('Database changed to: ' + dbName);
                //$('#internal-query-output').empty();
                const internalQueryOutput = $('#internal-query-output');
                internalQueryOutput.empty(); // Clear previous content
                const bubble = $('<div class="query-bubble-string"></div>').text(`
                    SELECT id_bd FROM General_BD_Tables WHERE db_name = '${dbName}';`);
                internalQueryOutput.append(bubble); // Add the new query bubble
                const bubble3 = $('<div class="query-bubble-string"></div>').text(`
                    SELECT table_name FROM General_TABLE_Tables WHERE db_id  = (SELECT id_bd FROM General_BD_Tables WHERE db_name = '${dbName}';`);
                internalQueryOutput.append(bubble3); // Add the new query bubble
                const internalresutOutput = $('#result-output');
                internalresutOutput.empty(); // Clear previous content
                const bubble1 = $('<div class="query-bubble-string"></div>').text(`
                "database selected"`);
                internalresutOutput.append(bubble1);
                // Ensure the internal query section is visible
                internalresutOutput.show();
            } else {
                alert('Failed to switch database: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Error while switching database: ' + error);
        }
    });

    // Step 2: Set the SQL query in the textarea for showing tables
    //document.getElementById('sql-query').value = `USE ${dbName};\nSHOW TABLES;`;

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



function displayTableData(response) {
    if (response.success) {
        let resultHtml = '<table border="1" cellpadding="10" cellspacing="0"><thead><tr>';
        
        // Create table headers from columns
        response.columns.forEach(function(column) {
            resultHtml += `<th>${column}</th>`;
        });
        resultHtml += '</tr></thead><tbody>';

        // Get the number of rows from the first column's data length
        let rowCount = response.data[response.columns[0]].length;
        for (let i = 0; i < rowCount; i++) {
            resultHtml += '<tr>';
            response.columns.forEach(function(column) {
                resultHtml += `<td>${response.data[column][i]}</td>`;
            });
            resultHtml += '</tr>';
        }

        resultHtml += '</tbody></table>';
        $('#result-output').html(resultHtml); // Display the table in a specific div
    } else {
        alert('Error: ' + response.message);
    }
}





        // Function to populate the query input area when a history entry is clicked
        function populateQuery(query) {
            document.getElementById('sql-query').value = query;
        }


        function clearResults() {
            // Clear the results displayed in the result-output div
            document.getElementById('result-output').innerText = '';
            document.getElementById('sql-query').value = ''; // Optionally clear the query input as well
            document.getElementById('internal-query-output').innerText = ''; // Optionally clear the query input as well

        }
        // Assuming you have a div with a specific class for each database


    </script>
</body>

</html>