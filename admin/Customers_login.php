<?php
session_start();
include "connection.php";
?>

<?php
$msg = " ";
if(isset($_POST["submit"])){
    include "connection.php";
    
    $email = $_POST['email'];
    $password = $_POST['pass'];

    $sql = "SELECT * FROM Customers WHERE CEmail='$email' AND CPassword='$password'; ";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        if($row = $result->fetch_assoc()) {
          $_SESSION["CustomerID"] = $row["CustomerID"];
          $_SESSION["CustomerName"] = $row["CustomerName"];
          echo $_SESSION["CustomerName"];
          //header("location:catalog.php?filterby=0");
        } else {
            $msg = '<div class="alert alert-dismissible alert-danger">
            <strong>Oh snap!</strong> Invalid E-mail / Password. Change some thing up try submitting again.
          </div>
          ';
        }
    }
    else{
        $msg = '<div class="alert alert-dismissible alert-danger">
        <strong>Oh snap!</strong> Invalid E-mail / Password. Change some thing up try submitting again.
      </div>';
    }
}
$conn->close();
?>