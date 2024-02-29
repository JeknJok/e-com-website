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
$OrderID=$_GET['OrderID'];
$sql = "SELECT * FROM OrderDetails WHERE OrderID = $OrderID";
$result = $conn->query($sql);
$total = 0;
$totalqty = 0;

if ($result->num_rows > 0) {
  echo "<table class='table table-primary'><tr><th>Product Code</th><th>Name</th><th>Price</th><th>Quantity</th>";
  while($row = $result->fetch_assoc()) {
      $pcode = $row['pcode'];
      $pprice = $row['pprice'];
      $qty = $row['qty'];
      $pname = $row['pname'];
      $total = $total + $pprice*$qty;
      $totalqty =  $totalqty +$qty; 
      

    echo "<tr class='table table-secondary'><td>$pcode</td><td>$pname</td><td>Rp.".number_format($pprice).".00-</td><td>$qty</td></tr>";

  }
  echo "<tr class='table-dark'><td>Total:</td><td><text class='text-muted'><text></td><td>Rp.".number_format($total).".00-</td><td>$totalqty <text class='text-muted'>Items<text></td></tr>";
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