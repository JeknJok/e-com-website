<?php
    include "navbar_admin.php";
    include "connection.php";

    $sql = "SELECT * FROM Orders";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  echo "<table class='table table-hover'><tr><th>OrderID</th><th>OrderDate</th><th>Ordered By Customer ID</th><th>OrderStatus</th><th>Check Paid</th><th>Uncheck Paid</th>";
  while($row = $result->fetch_assoc()) {
      $id = $row['OrderID'];
      $orderDate = $row['OrderDate'];
      $customerID = $row['CustomerID'];
      $orderStatus = $row['OrderStatus'];
      $update = "<a href ='check_paid.php?OrderID=$id'>Check Paid</a>" ;
      $delete = "<a href ='uncheck_paid.php?OrderID=$id'>Uncheck Paid</a>" ;
      $details= "<a href ='OrderDetails_list.php?OrderID=$id'>Details</a>" ;

    echo "<tr><td>$id</td><td>$orderDate</td><td>$customerID</td><td>$orderStatus</td><td>$update</td><td>$delete</td></tr>";
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

</body>
</html>