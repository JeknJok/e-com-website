<?php
include "connection.php";
include "navbar_public.php";
?>

<?php
$msg = " ";
if(isset($_POST["submit"])){
    include "connection.php";
    
    $email = $_POST['email'];
    $password = $_POST['pass'];

    $sql = "SELECT * FROM users_tb WHERE email='$email' AND pass='$password'; ";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        if($row = $result->fetch_assoc()) {
            header("location:user_list.php");
        } else {
            $msg = "Invalid e-mail or password.";
        }
    }
    else{
        $msg = "Invalid e-mail or password.";
    }
}
$conn->close();
?>
