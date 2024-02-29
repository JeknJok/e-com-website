<?php
include "connection.php";

// sql to delete a record
if (isset($_GET["CustomerID"])){
    $id=$_GET['CustomerID'];
    $sql = "DELETE FROM Customers WHERE id=$id";

    if ($conn->query($sql) === TRUE) {
      header("location: Customers_list.php");
    } else {
      echo "Error deleting record: " . $conn->error;
    }

    $conn->close();

}
else{
  echo "invalid ID";
}

?>