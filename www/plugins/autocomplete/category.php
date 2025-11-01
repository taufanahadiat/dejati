<?php
    if (isset($_GET['q'])) {
        $param = strtolower($_GET['q']);
        $type = $_REQUEST['type'];

        // Include database connection
		include __DIR__ . "/../../../include/db.php";
        // Check database connection
        if (!$consql) {
            die("Database connection failed: " . mysqli_connect_error());
        }

        // Prepare the query based on the type
        if ($type != 'sparepart') {
            $stmt = $consql->prepare("SELECT category FROM any_file_up WHERE type = ? AND category LIKE ? GROUP BY category");
            $searchParam = "%$param%";
            $stmt->bind_param("ss", $type, $searchParam);
        } else {
            $stmt = $consql->prepare("SELECT category FROM sp_code WHERE category LIKE ?");
            $searchParam = "%$param%";
            $stmt->bind_param("s", $searchParam);
        }

        // Execute the query
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($list = $result->fetch_array(MYSQLI_NUM)) {
                if (strpos(strtolower($list[0]), $param) !== false) {
                    echo "$list[0]\n";
                }
            }
        } else {
            echo "Error executing query: " . $stmt->error;
        }

        // Close the statement and connection
        $stmt->close();
        $consql->close();

        // Output "New Category" at the end
        echo "New Category";
    }
?>