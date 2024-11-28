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

        h2, h3 {
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
            padding: 12px;
            margin-bottom: 12px;
            border-radius: 8px;
            cursor: pointer;
            background-color: #f8f9fa;
            transition: background-color 0.3s, transform 0.3s;
        }

        .database-item:hover {
            background-color: #e9ecef;
            transform: translateX(10px);
        }

        .table-item {
            padding: 12px;
            margin-bottom: 12px;
            border-radius: 8px;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.3s;
        }

        .table-item:hover {
            background-color: #e9ecef;
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
            margin-bottom: 20px; /* Optional for spacing */
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
            height: 50%;
        }

        .content-half {
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
            background-color: #f8f9fa;
            padding: 12px;
            margin-bottom: 12px;
            border-radius: 8px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: background-color 0.3s, transform 0.3s;
        }

        .log-entry:hover {
            background-color: #e9ecef;
            transform: translateX(5px);
        }
       
    </style>
</head>
<body>

<div class="sidebar">
<h2>Databases</h2>
    <div class="middle">
        
        @foreach($databases as $dbId => $dbName)
            <div class="database-item" onclick="loadTables({{ $dbId }})">{{ $dbName }}</div>
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
            <button >Run Query</button>
            <button onclick="clearResults()">Clear</button>
        </div>
        <textarea id="sql-query" class="query-input" placeholder="Write your SQL query here..."></textarea>
    </div>

    <div class="content">
        <div class="content-half query-results">
            <h2>Query Result</h2>
            <div id="result-output"></div>
        </div>

        <div class="content-half query-results">
            <h2 id="query-command-title">Hidden Query </h2> 
        </div>

    </div>
</div>

<div class="query-log">
    <h2>Query History</h2>
    <div class="log-entry" onclick="populateQuery('SELECT * FROM Table 1')">SELECT * FROM Table 1</div>
    <div class="log-entry" onclick="populateQuery('SELECT * FROM Table 2 WHERE Column1 = \'Value\'')">SELECT * FROM Table 2 WHERE Column1 = 'Value'</div>
    <div class="log-entry" onclick="populateQuery('SELECT COUNT(*) FROM Table 3')">SELECT COUNT(*) FROM Table 3</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Optional function to load table content (this could be implemented as needed)
// Function to load tables for a given database
function loadTables(dbId) {
    $.get('/tables/' + dbId)
        .done(function(data) {
            const tableSelection = document.getElementById('table-selection');
            tableSelection.innerHTML = '';

            if (data.tables && data.tables.length > 0) {
                data.tables.forEach(function(table) {
                    const tableDiv = document.createElement('div');
                    tableDiv.classList.add('table-item');
                    tableDiv.innerText = table;

                    // When a table is clicked
                    tableDiv.onclick = () => {
                        // Change the title of the query command section
                        document.getElementById('query-command-title').innerText = `Content of ${table}`;
                        
                        // Fetch and display the content for this table
                        fetchTableContent(table)
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



// Function to populate the query input area when a history entry is clicked
function populateQuery(query) {
    document.getElementById('sql-query').value = query;
}


function clearResults() {
    // Clear the results displayed in the result-output div
    document.getElementById('result-output').innerText = '';
    document.getElementById('sql-query').value = ''; // Optionally clear the query input as well
}



</script>
</body>
</html>
