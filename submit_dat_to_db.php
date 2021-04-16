<?php
// Store metadata on MS SQL server to allow other people to read it and to speed up process
$server = '02cpt-wwdb01.m24.media24.com';
$database = 'Welkom Yizani Beta 2';
$user = 'username';
$password = 'password';

$connection = odbc_connect("Driver={SQL Server Native Client 10.0};Server=$server;Database=$database;", $user, $password);

?>