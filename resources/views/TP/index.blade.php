<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern SQL Client Interface</title>
    <style>
        /* Add the same styles here as you have in your original code */
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

        /* Sidebar for Databases */
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

        /* Main Content Area */
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

        /* Query History Section */
        .query-log {
            background-color: #fff;
            border-left: 1px solid #ddd;
            box-shadow: -2px 0 15px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 20px;
            flex-shrink: 0; /* Prevent shrinking */
            flex-grow: 0;   /* Prevent growing */
            width: 350px;   /* Fixed width */
            overflow-y: auto; /* Add scroll if content overflows */
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

<!-- Sidebar for Databases -->
<div class="sidebar">
    <h2>Databases</h2>
    
    <!-- Loop through databases passed from the controller -->
    @foreach($databases as $dbId => $dbName)
        <div class="database-item" onclick="loadTables({{ $dbId }})">{{ $dbName }}</div>
    @endforeach

    <div class="tables-section">
        <h3>Tables</h3>
        <div id="table-selection"></div> <!-- This will display tables based on the selected db -->
    </div>
</div>

<!-- Main Content -->
<div class="main">
    <div class="query-section">
        <h2>SQL Query</h2>
        <div class="query-toolbar">
            <button onclick="showQueryCommands()">Run Query</button>
            <button onclick="clearResults()">Clear</button>
        </div>
        <textarea id="sql-query" class="query-input" placeholder="Write your SQL query here..."></textarea>
    </div>

    <div class="content">
        <div class="content-half query-results">
            <h2>Query Result</h2>
            <div id="result-output">Click a table or run a query to see results here.</div>
        </div>

        <div class="content-half query-results">
            <div id="table-content">Select a table or run a query to see content here.</div>
        </div>
    </div>
</div>

<!-- Query History -->
<div class="query-log">
    <h2>Query History</h2>
    <div class="log-entry" onclick="populateQuery('SELECT * FROM Table 1')">SELECT * FROM Table 1</div>
    <div class="log-entry" onclick="populateQuery('SELECT * FROM Table 2 WHERE Column1 = \'Value\'')">SELECT * FROM Table 2 WHERE Column1 = 'Value'</div>
    <div class="log-entry" onclick="populateQuery('SELECT COUNT(*) FROM Table 3')">SELECT COUNT(*) FROM Table 3</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function showQueryCommands() {
        const sqlQuery = document.getElementById('sql-query').value;

        // Send the query to the server via AJAX
        $.post('/save-query', { sql_query: sqlQuery })
            .done(function(response) {
                alert(response.message);  // Show success message
                loadQueryHistory(); // Reload the query history after saving
            })
            .fail(function(xhr, status, error) {
                alert('Failed to save query: ' + error);
            });
    }

    function loadQueryHistory() {
        // Fetch the query history from the server
        $.get('/query-history')
            .done(function(response) {
                const queryHistoryContainer = document.querySelector('.query-log');
                queryHistoryContainer.innerHTML = '';  // Clear the current history
                
                // Loop through each query and add it to the history container
                response.queryHistory.forEach(function(query) {
                    const queryDiv = document.createElement('div');
                    queryDiv.classList.add('log-entry');
                    queryDiv.innerText = query.content_query + " (Executed at: " + query.timestamp_insert + ")";
                    queryHistoryContainer.appendChild(queryDiv);
                });
            })
            .fail(function(xhr, status, error) {
                alert('Failed to load query history: ' + error);
            });
    }

    // Call loadQueryHistory when the page loads to display the most recent queries
    $(document).ready(function() {
        loadQueryHistory();
    });

    function clearResults() {
        document.getElementById('result-output').innerHTML = "Results cleared!";
        document.getElementById('table-content').innerHTML = "Table content cleared!";
    }

    function populateQuery(query) {
        document.getElementById('sql-query').value = query;
    }
</script>

</body>
</html>
