<?php
include "navbar_admin.php";
if(isset($_POST["submit"])){
    include "connection.php";
    
    $pprice = $_POST['pprice'];
    $pimage = $_POST['pimage'];
    $pname = $_POST['pname'];
    $pdesc = $_POST['pdesc'];
    $pcategory= $_POST['pcategory'];

    if ($pprice != null 
    && $pname != null 
    && $pimage != null
    && $pdesc != null
    && $pcategory != null) {
      $sql = "INSERT INTO Products (pname,
      pprice,
      pimage,
      pdesc,
      pcategory)
      VALUES ('$pname', $pprice,'$pimage','$pdesc','$pcategory')";

      if ($conn->query($sql) === TRUE) {
          header("location:products_list.php");
      } else {
          echo "Error: " . $sql . "<br>" . $conn->error;
      }

      $conn->close();
    }

}
?>

<div class="container">
  <h2>New Product:</h2>
  <text class="text-muted"> Register your new product by filling all the information here:
  </text>
  <form action="products_insert.php" method="post">
    <div class="form-group">
      <div class="form-floating mb-3">
        <input type="text" name="pname" class="form-control" id="floatingInput" placeholder="Product Name">
        <label for="floatingInput">Product Name</label>
      </div>
      <div class="form-floating mb-3">
        <input type="text" name="pimage" class="form-control" id="floatingInput" placeholder="pimagelink">
        <label for="floatingInput">Product Image Link</label>
      </div>
      <div class="form-floating mb-3">
        <input type="text" name="pprice" class="form-control" id="floatingInput" placeholder="price per unit">
        <label for="floatingInput">Price per Unit</label>
      </div>
      <div class="form-floating mb-3">
        <input type="text" name="pdesc" class="form-control" id="floatingInput" placeholder="Description">
        <label for="floatingInput">Description</label>
      </div>
      <div class="form-floating mb-3">
        <input type="text" name="pcategory" class="form-control" id="floatingInput" placeholder="Category">
        <label for="floatingInput">Category</label>
      </div>
    </div>
    <div class="form-group form-check">
    </div>
    <button type="submit" name="submit" class="btn btn-primary">Submit</button>
    <div class="alert alert-dismissible alert-danger">
        Your data won't be recorded if you didn't fill <strong>all</strong> the information requested.
    </div>
    
  </form>
</div>

</body>
</html>