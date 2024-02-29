<?php
  session_start();
  if (!isset($_SESSION["CustomerID"])){
    header("location:index.php");
  }
  //tendang keluar klo blm ad SESSION
?>
<?php
include "navbar_customer.php";
include "connection.php";
$cid = $_SESSION['CustomerID'];
$sql = "SELECT * FROM Orders WHERE CustomerID=$cid AND OrderStatus= 'PAID' ";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  echo "<table class='table table-primary'><tr><th>Order ID</th><th>Ordered At</th><th>Order Status</th><th>See Details</th>";
  while($row = $result->fetch_assoc()) {
      $oid = $row['OrderID'];
      $orderDate = $row['OrderDate'];
      $orderStatus = $row['OrderStatus'];
      $details= "<a class='btn btn-info' href='OrderDetails_list.php?OrderID=$oid' role='button'>Details</a" ;
    echo "<tr class ='table table-secondary'><td>$oid</td><td>$orderDate</td><td>$orderStatus</td><td>$details</td></tr>";
  }
  echo "</table>";

} else {
  echo "0 results";
}
$conn->close();
?>

    <div class="col-sm-2">

    </div>
  </div>
</div>
<div class="alert alert-dismissible alert-info">
      <h4 class="alert-heading">Info</h4>
        The History page shows your past transactions that are already Completed.
      </div>
</body>
</html>