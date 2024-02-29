<?php
include "connection.php";

// sql to create table
$sql = "CREATE TABLE OrderDetails (
OrderDetailsID INT(16) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
OrderID INT(16) NOT NULL,
pcode INT(6) NOT NULL,
pprice INT(30) NOT NULL,
qty INT(10) NOT NULL,
pname VARCHAR (100) NOT NULL,
reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
  echo "Table Orders created successfully";
} else {
  echo "Error creating table: " . $conn->error;
}

$conn->close();
?>