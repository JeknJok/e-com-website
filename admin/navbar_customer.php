<?php
  $Cname =  $_SESSION["ContactName"];
?>

<!DOCTYPE html>

<html lang="en">
<head>
  <title>M-A-E-Commerce</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://bootswatch.com/5/lux/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</head>
<style>
img {
  display: block;
  margin-left: auto;
  margin-right: auto;
}

</style>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">M-A-E-Commerce</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarColor02" aria-controls="navbarColor02" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarColor02">
      <ul class="navbar-nav me-auto">          
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">          <?php
              echo "$Cname";
          ?></a>
          <div class="dropdown-menu">
          <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="Customers_profile.php">Update Personal Info</a>
            <a class="dropdown-item" href="Customers_history.php">Order History</a>
            <a class="dropdown-item" href="Customers_orders.php">Active Orders</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="logout.php">LOGOUT</a>
          </div>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="catalog.php?filterby=0">Shop</a>
        </li>
      </ul> 
      <form class="d-flex" action = "catalog.php" method ="post">
        <input class="form-control me-sm-2" type="text" name ="search" placeholder="Search Catalog">
        <button class="btn btn-secondary my-2 my-sm-0" type="submit">Search</button>
      </form>
    </div>
  </div>
</nav>
