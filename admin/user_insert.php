<?php
include "connection.php";
include "navbar_admin.php";

if(isset($_POST["submit"])){
    
    $firstname = $_POST['firstName'];
    $lastname = $_POST['lastName'];
    $email = $_POST['email'];
    $password = $_POST['pass'];

    $sql = "INSERT INTO users_tb (firstname, lastname, email, pass)
    VALUES ('$firstname', '$lastname' , '$email', '$password')";

    if ($conn->query($sql) === TRUE) {
        header("location:user_list.php");
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();

}
?>
<div class="container">
  <h2>User Sign Up</h2>
  <form action="user_insert.php" method="post">
    <div class="form-group">
      <label for="firstName">First Name:</label>
      <input type="firstName" class="form-control" id="firstName" placeholder="Enter your First Name" name="firstName">
    </div>
    <div class="form-group">
      <label for="lastName">First Name:</label>
      <input type="lastName" class="form-control" id="lastName" placeholder="Enter your Last Name/Surname" name="lastName">
    </div>
    <div class="form-group">
      <label for="email">Email:</label>
      <input type="email" class="form-control" id="email" placeholder="Enter email" name="email">
    </div>
    <div class="form-group">
      <label for="pwd">Password:</label>
      <input type="password" class="form-control" id="pass" placeholder="Enter password" name="pass">
    </div>
    <div class="form-group form-check">
    </div>
    <button type="submit" name="submit" class="btn btn-primary">Submit</button>
  </form>
</div>

</body>
</html>
