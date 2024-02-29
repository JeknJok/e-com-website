<?php

// ambil data file
$namaFile = "img_".$_POST["id"].".jpeg" ; 
$namaSementara = $_FILES['berkas']['tmp_name'];

// tentukan lokasi file akan dipindahkan
$dirUpload = "../assets/uploads/";

// pindahkan file
$terupload = move_uploaded_file($namaSementara, $dirUpload.$namaFile);

if ($terupload) {
    echo "Upload Success<br/>";
    echo "Link: <a href='".$dirUpload.$namaFile."'>".$namaFile."</a>";
    echo "<br> <a href='../admin/products_list.php'> Return.. </a> ";
} else {
    echo "Upload Failed!";
    echo "<br> <a href='../admin/products_list.php'> Return.. </a> ";
}

?>