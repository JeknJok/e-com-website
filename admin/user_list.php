    <?php
    include "connection.php";
    include "navbar_admin.php";

    $sql = "SELECT * FROM users_tb";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  echo "<table class='table table-hover'><tr><th>No</th><th>First Name</th><th>Last name</th><th>E-mail address</th><th>Update</th><th>Delete</th></tr>";
  while($row = $result->fetch_assoc()) {
      $id = $row['id'];
      $update = "<a href ='user_update.php?id=$id'>Update</a>" ;
      $delete = "<a href ='user_delete.php?id=$id'>Delete</a>" ;
      $firstname = $row['firstname'];
      $lastname = $row['lastname'];
      $email = $row['email'];
    echo "<tr><td>$id</td><td>$firstname</td><td>$lastname</td><td>$email</td><td>$update</td><td>$delete</td></tr>";
  }
  echo "</table>";
  echo "<a href ='user_insert.php'>Insert New Data</a> <br>";

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
