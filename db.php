<?php

$servername = "localhost";
$username = "root";
$password = "";
$database= "Football";



try{
    
    //Connect to database
    $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Database Connection successful :)";
} 
catch(PDOException $e){
    echo "Connection to database failed: " . $e->getMessage();
}

?>