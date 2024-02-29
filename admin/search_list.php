<?php
include "navbar_admin.php";
include "connection.php";

$keyword = $_POST['search'];
$sql = "SELECT * FROM Products WHERE pname LIKE '%$keyword%' ";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  echo "<table class='table table-hover'><tr><th>Product Code</th><th>Category</th><th>Product Image</th><th>Product Name</th><th>Product Desc.</th><th>Price per unit</th></tr>";
  while($row = $result->fetch_assoc()) {
      $id = $row['pcode'];
      $pcategory=$row['pcategory'];
      $productname = $row['pname'];
      $pdesc = $row['pdesc'];
      $pprice = $row['pprice'];
      $image = $row['pimage'];
      $pimage = "<img src = '$image' width = '50px' height= '50px'>";
    echo "<tr><td>$id</td><td>$pcategory</td><td>$pimage</td><td>$productname</td><td>$pdesc</td><td>$pprice</td></tr>";
  }
  echo "</table>";
  echo
  "<form class='d-flex' action='products_insert.php'>
  <button class='btn btn-info' type='submit'>Insert New Product</button>
  </form>";

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
