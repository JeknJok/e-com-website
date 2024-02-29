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
$sql = "SELECT * FROM Orders WHERE CustomerID=$cid AND OrderStatus='INVOICE' ";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
  echo "<table class='table table-primary'><tr><th>OrderID</th><th>Order Time</th><th>Ordered By Customer ID</th><th>OrderStatus</th><th>Details</th><th>Cancel Order</th>";
  while($row = $result->fetch_assoc()) {
      $oid = $row['OrderID'];
      $orderDate = $row['OrderDate'];
      $orderStatus = $row['OrderStatus'];
      $details= "<a class='btn btn-info' href='OrderDetails_list.php?OrderID=$oid' role='button'>Details</a>" ;
      $cancel= "<a class='btn btn-danger' href ='Customers_cancelOrder.php?OrderID=$oid' role='button'>Cancel</a>" ;

    echo "<tr class='table-secondary'><td>$oid</td><td>$orderDate</td><td>$cid (You)</td><td>$orderStatus</td><td>$details</td><td>$cancel</td></tr>";
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
        Orders that aren't paid within 24 Hours will be auto-magically cancelled.
      </div>
</body>
</html>