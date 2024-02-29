<?php
include "connection.php";

// sql to create table
$sql = "CREATE TABLE Orders (
OrderID INT(16) UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
OrderDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
CustomerID INT(20) NOT NULL,
OrderStatus VARCHAR(10) NOT NULL,
reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
  echo "Table Orders created successfully";
} else {
  echo "Error creating table: " . $conn->error;
}

$conn->close();
?>