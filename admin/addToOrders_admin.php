<?php //add to table cart 
    session_start();
    include "connection.php";
    $orderID = $_POST["oid"];
    $customerID = $_POST["cid"];
    if ( $orderID=="new" ){
        
        $sql = "INSERT INTO Orders (CustomerID , OrderStatus)
        VALUES ('$customerID', 'INVOICE')";

        if ($conn->query($sql) === TRUE) {
            $sql = "SELECT * FROM Orders ORDER BY OrderID DESC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                if($row = $result->fetch_assoc()) {
                    $orderID = $row['OrderID'];
                }
            }

        }
    }
        
    $pcode = $_POST["pcode"];
    $qty = $_POST["qty"];
    
    $sql = "SELECT * FROM OrderDetails WHERE OrderID = $orderID AND pcode = $pcode";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        if($row = $result->fetch_assoc()) {
            $new_qty = $row['qty'];
            $new_qty = $qty + $new_qty;
            $sql = "UPDATE OrderDetails SET qty = $new_qty WHERE OrderID = $orderID AND pcode = $pcode";
        }
    }
    else {
        $pprice = $_POST["pprice"];
        $pname = $_POST["pname"];
        $sql = "INSERT INTO OrderDetails (OrderID, pcode, pprice, qty, pname)
        VALUES ('$orderID' ,'$pcode', $pprice, $qty,'$pname')";
    }

    if ($conn->query($sql) === TRUE) {
        
    header("location:catalog_admin.php?oid=$orderID&cid=$customerID");
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }       

    

    $conn->close(); 
?>


