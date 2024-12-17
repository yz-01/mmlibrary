<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// ... existing code ...
date_default_timezone_set("Asia/Kuala_Lumpur");
//include('auth.php');
$servername = "localhost";
/*$username = "ccrdskmy_loansw";
$password = "gq5QBO,Z8UQN";
$dbname = "ccrdskmy_loan_software";*/
$username = "root";
$password = "root";
$dbname = "crm";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
} 
// $host_name = $_SERVER['SERVER_NAME'];
// $server_ssl = $_SERVER['REQUEST_SCHEME'];
// $url = $server_ssl.'://'.$host_name;

// define("SITEURL",$url);

// $select_superadmin = "SELECT * from domain_list_setting where domain_name='$host_name'";
// $row_superadmin = $conn->query($select_superadmin);
// $result_superadmin = mysqli_fetch_assoc($row_superadmin);

// $domain_status = $result_superadmin['status'];
// $table_prefix = $result_superadmin['table_prefix'];
// $first_method = $result_superadmin['first_method'];
// $agent_add_loan = $result_superadmin['agent_add_loan'];
