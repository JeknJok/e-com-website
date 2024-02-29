<?php
  session_start();
  if (!isset($_SESSION["CustomerID"])){
    header("location:../index.php");
  }
  //tendang keluar klo blm ad SESSION
?>

<?php

    include "navbar_customer.php";
    include "connection.php";
    if ( isset($_GET["oid"]) ){
      $orderID = $_GET["oid"];
    }
    else{
      $orderID = "new";
    }
    echo'
    <ul class="nav nav-pills">
    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">FILTER BY CATEGORY</a>
    <div class="dropdown-menu">
      <a class="dropdown-item" href="catalog.php?filterby=1">Electronics</a>
      <a class="dropdown-item" href="catalog.php?filterby=2">Toys</a>
      <div class="dropdown-divider"></div>
      <a class="dropdown-item" href="catalog.php?filterby=0">All</a>
    </div>

    <ul>';
    if (isset($_GET["filterby"])){
      $filterby = $_GET["filterby"];
    }else {
      $filterby = 0 ;
    }
    if (isset($_POST['search'])){
      $keyword = $_POST["search"];
    }else{
      $keyword = '';
    }
    if ($filterby < 1){
      $sql = "SELECT * FROM Products WHERE pname LIKE '%$keyword%'";
      $result = $conn->query($sql);
    }
    else if ($filterby == 1){
      $sql = "SELECT * FROM Products WHERE pcategory = 'ELECTRONICS' ";
      $result = $conn->query($sql);
    }
    else if ($filterby == 2){
      $sql = "SELECT * FROM Products WHERE pcategory = 'TOYS' ";
      $result = $conn->query($sql);
    }

if ($result->num_rows > 0) {
    echo "<table> <tr>";

    $count= 0 ;

  while ($row = $result->fetch_assoc()){
    $pcode = $row['pcode'];
    $image = $row['pimage'];
    $pprice = $row['pprice'];
    $pname = $row['pname'];
    $pdesc = $row['pdesc'];
    $reg_date = $row['reg_date'];
    $pcategory = $row['pcategory'];

    echo "<td>
    <div class='card bg-secondary mb-3' style='max-width: 20rem;'>
    <h3 class='card-header'>$pcategory</h3>
    <div class='card-body'>
      <h5 class='card-title'>$pname</h5>
      <h6 class='card-subtitle text-muted'>ready for greatness.</h6>
    </div>
    <div class='card border-secondary mb-3' >
    <img src = '$image' width = '300px' height= '300px' class='centre'>
    </div>
    <div class='card-body'>
    <div class='accordion' id='accordionExample'>
    <div class='accordion-item'>
      <h2 class='accordion-header' id='headingOne'>
        <button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#collapseOne' aria-expanded='false' aria-controls='collapseOne'>
          Product Description
        </button>
      </h2>
      <div id='collapseOne' class='accordion-collapse collapse' aria-labelledby='headingOne' data-bs-parent='#accordionExample' style=''>
        <div class='accordion-body'>
          <strong>$pname</strong> <br><br> $pdesc
        </div>
      </div>
    </div>
    </div>
    </div>
    <ul class='list-group list-group-flush'>
      <li class='list-group-item'>Rp.".number_format($pprice).".00- <text class='text-muted'>per Item</text></li>
      <li class='list-group-item'>GREAT VALUE.</li>
      <li class='list-group-item'>TOP CHOICE.</li>
    </ul>
    <div class='card-body'>
      <form class='d-flex' action = 'addToOrders.php' method='post'> 
      <input type='text' class='form-control me-sm-2' size='5' value='1' name = 'qty' placeholder='Quantity'>
      <input type='hidden' name='pcode' value='$pcode'>
      <input type='hidden' name='oid' value='$orderID'> 
      <input type='hidden' name='pprice' value='$pprice'> 
      <input type='hidden' name='pname' value='$pname'> 
      <input type='submit' name='submit' class='btn btn-success' value='Add to Cart!'>
      </form>
    </div>
    <div class='card-footer text-muted'>
      $reg_date
    </div>
  </div>
    </td>";
    $count ++ ;
    if ($count %4 == 0){
        echo "</tr> <tr> ";
    }
    
    }
  echo "</tr> </table>";

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
