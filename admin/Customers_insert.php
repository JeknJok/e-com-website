


<?php
include "navbar_public.php";
if(isset($_POST["submit"])){
    include "connection.php";
    
    $CustomerName = $_POST['CustomerName'];
    $ContactName = $_POST['ContactName'];
    $CAddress = $_POST['CAddress'];
    $City = $_POST['City'];
    $PostalCode = $_POST['PostalCode'];
    $Country = $_POST['Country'];
    $CEmail = $_POST['CEmail'];
    $CPassword = $_POST['CPassword'];

    if ($CustomerName != null 
    && $ContactName != null
    && $CAddress != null
    && $City != null
    && $PostalCode != null
    && $Country != null
    && $CEmail != null
    && $CPassword != null) {
      $sql = "INSERT INTO Customers (CustomerName,
      ContactName,
      CAddress,
      PostalCode,
      Country,
      CPassword,
      CEmail,
      City)
      VALUES ('$CustomerName','$ContactName','$CAddress','$PostalCode','$Country','$CPassword','$CEmail','$City')";

      if ($conn->query($sql) === TRUE) {
          header("location:catalog.php?filterby=0");
      } else {
          echo "Error: " . $sql . "<br>" . $conn->error;
      }

      $conn->close();
    }


}
?>

<div class="container">
  <h2>Register Now!</h2>
  <text class="text-muted">Register now Or <a href='../index.php#login'>Login Here</a> and start shopping in the most affordable e-commerce platform
    known to mankind! (Please dont use personal Information. This is only a portfolio website.)
  </text>
  <form action="Customers_insert.php" method="post">
    <div class="form-group">
      <div class="form-floating mb-3">
        <input type="text" name="CustomerName" class="form-control" id="floatingInput" placeholder="Full Name">
        <label for="floatingInput">Full Name (Remember - DO NOT use sensitive personal info)</label>
      </div>
      <div class="form-floating mb-3">
        <input type="text" name="ContactName" class="form-control" id="floatingInput" placeholder="Username">
        <label for="floatingInput">Username</label>
      </div>
      <div class="form-floating mb-3">
        <input type="text" name="CAddress" class="form-control" id="floatingInput" placeholder="Address">
        <label for="floatingInput">Address (Remember - DO NOT use sensitive personal info)</label>
      </div>
      <div class="form-floating mb-3">
        <input type="text" name="CEmail" class="form-control" id="floatingInput" placeholder="example@mail.com">
        <label for="floatingInput">example@email.com (you can use nonexistent e-mail here)</label>
      </div>
      <div class="form-floating mb-3">
        <input type="text" name="Country" class="form-control" id="floatingInput" placeholder="Country">
        <label for="floatingInput">Country (Remember - DO NOT use sensitive personal info) </label>
      </div>
      <div class="form-floating mb-3">
        <input type="text" name="City" class="form-control" id="floatingInput" placeholder="City">
        <label for="floatingInput">City (Remember - DO NOT use sensitive personal info)</label>
      </div>
      <div class="form-floating mb-3">
        <input type="text" name="PostalCode" class="form-control" id="floatingInput" placeholder="PostalCode">
        <label for="floatingInput">Postal Code (Remember - DO NOT use sensitive personal info)</label>
      </div>
      <div class="form-floating mb-3">
        <input type="text" name="CPassword" class="form-control" id="floatingInput" placeholder="CPassword">
        <label for="floatingInput">Password (16 Characters Max) </label>
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
