<?php
    include "connection.php";

    $OrderID = $_GET["OrderID"];

    $sql = "UPDATE Orders 
    SET OrderStatus = 'INVOICE' WHERE OrderID = $OrderID ";

    if ($conn->query($sql) === TRUE) {
        header('location:Orders_list.php');
      } else {
        echo " " . $conn->error;
      }

?>
