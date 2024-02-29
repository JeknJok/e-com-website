<?php
include "connection.php";

// sql to create table
$sql = "CREATE TABLE users_tb (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
firstname VARCHAR(30) NOT NULL,
lastname VARCHAR(30),
email VARCHAR(50) NOT NULL,
username VARCHAR(10) NOT NULL,
pass VARCHAR(10) NOT NULL,
mobile VARCHAR(13) NOT NULL,
reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
  echo "Table MyGuests created successfully";
} else {
  echo "Error creating table: " . $conn->error;
}

$conn->close();
?>