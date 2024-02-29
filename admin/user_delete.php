<?php
include "connection.php";

// sql to delete a record
if (isset($_GET["id"])){
    $id=$_GET['id'];
    $sql = "DELETE FROM users_tb WHERE id=$id";

    if ($conn->query($sql) === TRUE) {
      header("location: user_list.php");
    } else {
      echo "Error deleting record: " . $conn->error;
    }

    $conn->close();

}
else{
  echo "invalid ID";
}

?>