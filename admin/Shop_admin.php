    <?php
    include "navbar_admin.php";
    include "connection.php";

    $sql = "SELECT * FROM Customers";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  echo "<table class='table table-hover'><tr><th>No</th><th>Name</th><th>Username</th><th>Address</th><th>City</th><th>Postal Code</th><th>Country</th><th>Shop As</th></tr>";
  while($row = $result->fetch_assoc()) {
      $cid = $row['CustomerID'];
      $shop = "<a href ='catalog_admin.php?cid=$cid'>Shop As</a>";
      $CustomerName = $row['CustomerName'];
      $ContactName = $row['ContactName'];
      $CAddress = $row['CAddress'];
      $City = $row['City'];
      $PostalCode = $row['PostalCode'];
      $Country = $row['Country'];
    echo "<tr><td>$cid</td><td>$CustomerName</td><td>$ContactName</td><td>$CAddress</td><td>$City</td><td>$PostalCode</td><td>$Country</td><td>$shop</td></tr>";
  }
  echo "</table>";
  echo "<a href ='Customers_insert.php'>Insert New Data</a> <br>";

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
