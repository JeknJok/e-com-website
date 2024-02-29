<?php
  session_start();
  if (!isset($_SESSION["CustomerID"])){
    header("location:../index.php");
  }
  //tendang keluar klo blm ad SESSION
?>

<?php
include "navbar_admin.php";
include "connection.php";

$sql = "SELECT * FROM Customers";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  echo "<table class='table table-hover'><tr><th>No</th><th>Name</th><th>Username</th><th>Address</th><th>City</th><th>Postal Code</th><th>Country</th><th>Update</th><th>Delete</th></tr>";
  while($row = $result->fetch_assoc()) {
      $id = $row['CustomerID'];
      $update = "<a href ='Customers_update.php?id=$id'>Update</a>" ;
      $delete = "<a href ='Customers_delete.php?id=$id'>Delete</a>" ;
      $CustomerName = $row['CustomerName'];
      $ContactName = $row['ContactName'];
      $CAddress = $row['CAddress'];
      $City = $row['City'];
      $PostalCode = $row['PostalCode'];
      $Country = $row['Country'];
    echo "<tr><td>$id</td><td>$CustomerName</td><td>$ContactName</td><td>$CAddress</td><td>$City</td><td>$PostalCode</td><td>$Country</td><td>$update</td><td>$delete</td></tr>";
  }
  echo "</table>";
  echo "<a href ='Customers_insert.php'>Insert New Data</a> <br>";
  echo "<a href ='Customers_logout.php'>Logout</a>";

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
