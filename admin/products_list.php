<?php
include "navbar_admin.php";
include "connection.php";

$sql = "SELECT * FROM Products";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  echo "<table class='table table-hover'><tr><th>Product Code</th><th>Category</th><th>Product Image</th><th>Product Name</th><th>Product Desc.</th><th>Price per unit</th><th>Update</th><th>Delete</th><th>Upload</th></tr>";
  while($row = $result->fetch_assoc()) {
      $id = $row['pcode'];
      $pcategory=$row['pcategory'];
      $update = "<a href ='products_update.php?pcode=$id'>Update</a>" ;
      $delete = "<a href ='products_delete.php?pcode=$id'>Delete</a>" ;
      $productname = $row['pname'];
      $pdesc = $row['pdesc'];
      $pprice = $row['pprice'];
      $image = $row['pimage'];
      $pimage = "<img src = '$image' width = '50px' height= '50px'>";
    echo "<tr><td>$id</td><td>$pcategory</td><td>$pimage</td><td>$productname</td><td>$pdesc</td><td>$pprice</td><td>$update</td><td>$delete</td>
    <td><form action='../assets/upload.php' method='post' enctype='multipart/form-data'>
    Choose file: <input type='file' name='berkas' />
    <input type='submit' name='upload' value='upload'/>
    <input type='hidden' name='id' value='$id' />
    </form> </td></tr>";
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
