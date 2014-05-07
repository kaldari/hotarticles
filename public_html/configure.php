<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

/* Setup the mediawiki classes. */
require_once dirname(__FILE__) . '/../config.inc.php';
include ("header.inc.php");

$link = mysqli_connect($hotarticlesdb['host'], $hotarticlesdb['user'], $hotarticlesdb['pass'], $hotarticlesdb['dbname']);

print("<h2>Hot Article Subscriptions</h2>");
print("<p>Click on the name of a subscription to configure it.</p>");

$query = "SELECT * FROM hotarticles";
$result = mysqli_query($link, $query) or die(mysqli_error());

while ($row = mysqli_fetch_array ($result)) {
	print('<a href="editsubscription.php?id='.$row['id'].'">'.$row['source'].'</a><br/>');
}
print('<br/>');
print('[ <a href="addsubscription.php">Add a subscription</a> ]<br/>');
print('<br/>');
include ("footer.inc.php");
