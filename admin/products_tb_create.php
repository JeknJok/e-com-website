<?php
include "connection.php";

// sql to create table
$sql = "CREATE TABLE Products (
pcode INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
pcategory TEXT (20) NOT NULL,
pprice INT(30) NOT NULL,
pimage	VARCHAR(500) NOT NULL,
pname VARCHAR(30) NOT NULL,
pdesc VARCHAR(2000) NOT NULL,
reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
  echo "Table Products created successfully";
} else {
  echo "Error creating table Products: " . $conn->error;
}

$conn->close();
?>
