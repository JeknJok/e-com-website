<?php
include "connection.php";

// sql to create table
$sql = "CREATE TABLE Customers (
CustomerID INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
CustomerName VARCHAR(30) NOT NULL,
ContactName	VARCHAR(30) NOT NULL,
CAddress VARCHAR(500) NOT NULL,
City	VARCHAR(20) NOT NULL,
PostalCode VARCHAR(10),
Country VARCHAR(20) NOT NULL,
CPassword VARCHAR(16) NOT NULL,
CEmail VARCHAR(20) NOT NULL,
reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
  echo "Table Customers created successfully";
} else {
  echo "Error creating table: " . $conn->error;
}

$conn->close();
?>