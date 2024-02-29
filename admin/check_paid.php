<?php
  session_start();
  if (!isset($_SESSION["CustomerID"])){
    header("location:index.php");
  }
  //tendang keluar klo blm ad SESSION
?>

<?php
    include "connection.php";

    $OrderID = $_GET["OrderID"];

    $sql = "UPDATE Orders 
    SET OrderStatus = 'PAID' WHERE OrderID = $OrderID ";

    if ($conn->query($sql) === TRUE) {
        header('location:Orders_list.php');
      } else {
        echo " " . $conn->error;
      }

    
?>