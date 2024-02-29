<?php
include "connection.php";

// sql to create table
$sql = "CREATE TABLE Cart (
pcode INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
pprice INT(30) NOT NULL,
pname VARCHAR(30) NOT NULL,
qty INT(1000) NOT NULL,
reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
  echo "Table Cart created successfully";
} else {
  echo "Error creating table Cart: " . $conn->error;
}

$conn->close();
?>