<?php
include '../includes/config.php'; // Include database connection/config

// Auth: admin only
if (
    !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true ||
    ($_SESSION['role'] ?? '') !== 'Admin'
) {
    // Redirect or exit if not authorized (prevents log access)
    header('Location: ../index.php');
    exit;
}

// 1. Set headers for CSV download
$filename = 'audit_logs_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// 2. Open output stream
$output = fopen('php://output', 'w');

// 3. Define the column headers for the CSV
$headers = ['Timestamp', 'Username', 'Action', 'IP Address', 'Log ID'];
// Use fputcsv for safe handling of headers
fputcsv($output, $headers);

// 4. Fetch all audit logs (ordered by newest first)
$sql_logs = "
    SELECT a.timestamp, u.username, a.action, a.ip_address, a.log_id
    FROM audit_logs a
    JOIN users u ON u.user_id = a.user_id
    ORDER BY a.timestamp DESC
";
$logs_result = $conn->query($sql_logs);

if ($logs_result && $logs_result->num_rows > 0) {
    // 5. Loop through results and write to CSV
    while ($row = $logs_result->fetch_assoc()) {
        // We ensure the data matches the header order
        $log_data = [
            $row['timestamp'],
            $row['username'],
            $row['action'],
            $row['ip_address'] ?? 'N/A', // Handle case where IP might be NULL
            $row['log_id']
        ];
        fputcsv($output, $log_data);
    }
    $logs_result->free();
} else {
    // Write a note if no logs are found
    fputcsv($output, ['No audit logs found.']);
}

// 6. Close the output stream and exit
fclose($output);
$conn->close();
exit;