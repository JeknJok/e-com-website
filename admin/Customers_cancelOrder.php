<?php
include "connection.php";

// sql to delete a record
if (isset($_GET["OrderID"])){
    $id=$_GET['OrderID'];
    $sql = "DELETE FROM Orders WHERE OrderID=$id";

    if ($conn->query($sql) === TRUE) {
      $sql = "DELETE FROM OrderDetails WHERE OrderID=$id";

      header("location: Customers_orders.php");
    } else {
      echo "Error deleting record: " . $conn->error;
    }

    $conn->close();

}
else{
  echo "invalid ID";
}

?>