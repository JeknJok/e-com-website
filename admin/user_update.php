<?php
include "connection.php";
include "navbar_admin.php";

?>

<?php
if(isset($_POST["submit"])){
    include "connection.php";
    $id = $_POST['id'];
    $firstname = $_POST['firstName'];
    $lastname = $_POST['lastName'];
    $email = $_POST['email'];
    $password = $_POST['pass'];

    $sql = "UPDATE users_tb 
    SET firstname = '$firstname',
    lastname = '$lastname',
    email = '$email',
    pass = '$password'
    WHERE id= $id";

    if ($conn->query($sql) === TRUE) {
        header("location:user_list.php");
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

}
else{
    $id = $_GET['id'];
    $sql = "SELECT * FROM users_tb WHERE id=$id";
    $result = $conn->query($sql);

  if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $id = $_GET['id'];
        $firstname = $row['firstname'];
        $lastname = $row['lastname'];
        $email = $row['email'];
        $password = $row['pass'];
    }
  }
}
$conn->close();
?>

<div class="container">
  <h2>User Update Form</h2>
  <form action="user_update.php" method="post">
    <div class="form-group">
      <label for="id">ID:</label>
      <input type="id" class="form-control" id="id" value="<?php echo $id;?>" name="id">
    </div>
    <div class="form-group">
      <label for="firstName">First Name:</label>
      <input type="firstName" class="form-control" id="firstName" value="<?php echo $firstname;?>" name="firstName">
    </div>
    <div class="form-group">
      <label for="lastName">First Name:</label>
      <input type="lastName" class="form-control" id="lastName" value="<?php echo $lastname;?>" name="lastName">
    </div>
    <div class="form-group">
      <label for="email">Email:</label>
      <input type="email" class="form-control" id="email" value="<?php echo $email;?>" name="email">
    </div>
    <div class="form-group">
      <label for="pwd">Password:</label>
      <input type="password" class="form-control" id="pass" value="<?php echo $password;?>" name="pass">
    </div>
    <div class="form-group form-check">
    </div>
    <button type="submit" name="submit" class="btn btn-primary">Submit</button>
  </form>
</div>

</body>
</html>
