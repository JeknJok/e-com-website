<?php
  session_start();
  if (!isset($_SESSION["CustomerID"])){
    header("location:index.php");
  }
  //tendang keluar klo blm ad SESSION
?>
<?php
include "connection.php";
include "navbar_customer.php";
?>

<?php
if(isset($_POST["submit"])){
    $id = $_SESSION['CustomerID'];
    $CustomerName = $_POST['CustomerName'];
    $ContactName = $_POST['ContactName'];
    $CAddress = $_POST['CAddress'];
    $City = $_POST['City'];
    $PostalCode = $_POST['PostalCode'];
    $Country = $_POST['Country'];
    $CEmail = $_POST['CEmail'];
    $CPassword = $_POST['CPassword'];
    $City = $_POST['City'];

    $sql = "UPDATE Customers 
    SET CustomerName = '$CustomerName' ,
    ContactName = '$ContactName',
    CAddress = '$CAddress',
    PostalCode = '$PostalCode',
    Country = '$Country',
    CPassword = '$CPassword',
    CEmail = '$CEmail' 
    WHERE CustomerID = $id";

    if ($conn->query($sql) === TRUE) {
        header("location:Customers_profile.php");
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

}
else{
    $id = $_SESSION['CustomerID'];
    $sql = "SELECT * FROM Customers WHERE CustomerID = $id";
    $result = $conn->query($sql);

  if ($result->num_rows > 0) {
    if($row = $result->fetch_assoc()) {
        $CustomerName = $row['CustomerName'];
        $ContactName = $row['ContactName'];
        $CAddress = $row['CAddress'];
        $City = $row['City'];
        $PostalCode = $row['PostalCode'];
        $Country = $row['Country'];
        $CEmail = $row['CEmail'];
        $CPassword = $row['CPassword'];
        $City = $row['City'];
    }
  }
}
$conn->close();
?>

<div class="container">
  <h2></h2>
  <div class="alert alert-dismissible alert-info">
      <h4 class="alert-heading">Info</h4>
      You can Update your data here. Any changes won't be saved before you click "Update Data" button.
      </div>
  <form action="Customers_profile.php" method="post">
    <div class="form-group">
      <label for="CustomerName">Name:</label>
      <input type="text" value="<?php echo $CustomerName;?>"class="form-control" name="CustomerName"  placeholder="Enter your Full name" name="CustomerName">
    </div>
    <div class="form-group">
      <label for="ContactName">Username:</label>
      <input type="text" class="form-control"value="<?php echo $ContactName;?>"  name="ContactName" placeholder="Enter your UserName" name="ContactName">
    </div>
    <div class="form-group">
      <label for="CAddress">Address:</label>
      <input type="text" class="form-control"value="<?php echo $CAddress;?>" name="CAddress" placeholder="Enter your Adress" name="CAddress">
    </div>
    <div class="form-group">
      <label for="CEmail">Email:</label>
      <input type="text" class="form-control" value="<?php echo $CEmail;?>"name="CEmail" placeholder="Enter email" name="CEmail">
    </div>
    <div class="form-group">
      <label for="Country">Country:</label>
      <input type="text" class="form-control" value="<?php echo $Country;?>" name="Country" placeholder="Enter Country" name="Country">
    </div>
    <div class="form-group">
      <label for="Country">City:</label>
      <input type="text" class="form-control"value="<?php echo $City;?>" name="City" placeholder="Enter City" name="City">
    </div>
    <div class="form-group">
      <label for="PostalCode">PostalCode:</label>
      <input type="text" class="form-control" name="PostalCode" value="<?php echo $PostalCode;?>"placeholder="Enter Postal Code" name="PostalCode">
    </div>
    <div class="form-group">
      <label for="CPassword">Password:</label>
      <input type="text" class="form-control" name="CPassword" placeholder="Enter Password, 16 Characters Max" name="CPassword">
    </div>
    <div class="form-group form-check">
    </div>
    <button type="submit" name="submit" class="btn btn-primary">Update Data</button>
  </form>
</div>

</body>
</html>
