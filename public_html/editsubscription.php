<?php
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

// Set up configuration and wikipedia class
require_once dirname(__FILE__) . '/../config.inc.php';
require_once dirname(__FILE__) . '/../botclasses.php';

// Set up hotarticles database connection
$link = mysqli_connect($hotarticlesdb['host'], $hotarticlesdb['user'], $hotarticlesdb['pass'], $hotarticlesdb['dbname']);

// Instantiate wikipedia object
$wikipedia = new wikipedia();

// Log in to wikipedia
$wikipedia->login($enwiki['user'],$enwiki['pass']);

// Process form submission if needed
$error = '';
if ( isset( $_POST['source'] ) ) {
	$_POST['source'] = str_replace( '_', ' ', $_POST['source'] );

	$count = $wikipedia->categorypagecount( 'Category:' . $_POST['source'] );
	if ( $count > $maxArticles ) {
		$error = "Error: Category " . $_POST['source'] . " is too large.<br/>";
	} else {
		// Clean up posted values
		foreach($_POST as $key => $value) {
			$_POST[$key] = trim( mysqli_real_escape_string( $link, $value ) );
		}

		if ( $_POST['span_days'] <= 30 && $_POST['span_days'] > 0 && $_POST['article_number'] <= 100 ) {
			$query = "UPDATE hotarticles SET method = 'category', source = '$_POST[source]', article_number = '$_POST[article_number]', span_days = '$_POST[span_days]', target_page = '$_POST[target_page]', orange = '$_POST[orange]', red = '$_POST[red]' WHERE id = $_POST[id] LIMIT 1";
			$result = mysqli_query( $link, $query );
			if ( !$result ) {
				$error = 'Database error: ' . mysqli_error();
			} else {
				Header( 'Location:configure.php' );
			}
		} else {
			$error = 'Form input not valid. Please try again.';
		}
	}
}

include ("header.inc.php");

$query = "SELECT * FROM hotarticles where id = " . $_GET[id] . " LIMIT 1";
$subscriptionResult = mysqli_query( $link, $query );
$row = mysqli_fetch_array( $subscriptionResult );

print("<h2>Edit a Hot Article Subscription</h2>");
print("<p><i>Note: Currently subscriptions are limited to categories or templates with ".$maxArticles." or fewer pages.</i></p>");
if ($error) {
	print ("<p class=\"error\">".$error."</p>\n");
}
?>
<form name="form1" method="post">
	<table cellspacing="2" cellpadding="2" border="0">
		<tr>
			<td>Category Name:
				<a class="tt" href="#">
				<img height="12" width="12" border="0" src="images/help_icon.gif"/>
				<span class="tooltip">
					<span class="top"></span>
					<span class="middle">Without prefix. For example, "WikiProject Tulips articles"</span>
					<span class="bottom"></span>
				</span>
				</a>
			</td>
			<td><input name="source" value="<?php echo stripslashes($row['source']); ?>" type="text" size="40" maxlength="128"/></td>
		</tr>
		<tr>
			<td>Number of Articles:
				<a class="tt" href="#">
				<img height="12" width="12" border="0" src="images/help_icon.gif"/>
				<span class="tooltip">
					<span class="top"></span>
					<span class="middle">The number of articles you would like to appear in the published list</span>
					<span class="bottom"></span>
				</span>
				</a>
			</td>
			<td>
				<select name="article_number">
					<option value="1"<?php if ($row['article_number'] == "1") echo " selected=\"selected\""; ?>>1</option>
					<option value="2"<?php if ($row['article_number'] == "2") echo " selected=\"selected\""; ?>>2</option>
					<option value="3"<?php if ($row['article_number'] == "3") echo " selected=\"selected\""; ?>>3</option>
					<option value="4"<?php if ($row['article_number'] == "4") echo " selected=\"selected\""; ?>>4</option>
					<option value="5"<?php if (!$row['article_number'] || $row['article_number'] == "5") echo " selected=\"selected\""; ?>>5</option>
					<option value="6"<?php if ($row['article_number'] == "6") echo " selected=\"selected\""; ?>>6</option>
					<option value="7"<?php if ($row['article_number'] == "7") echo " selected=\"selected\""; ?>>7</option>
					<option value="8"<?php if ($row['article_number'] == "8") echo " selected=\"selected\""; ?>>8</option>
					<option value="9"<?php if ($row['article_number'] == "9") echo " selected=\"selected\""; ?>>9</option>
					<option value="10"<?php if ($row['article_number'] == "10") echo " selected=\"selected\""; ?>>10</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>Number of Days:
				<a class="tt" href="#">
				<img height="12" width="12" border="0" src="images/help_icon.gif"/>
				<span class="tooltip">
					<span class="top"></span>
					<span class="middle">How many days back shound edits be counted?</span>
					<span class="bottom"></span>
				</span>
				</a>
			</td>
			<td>
				<select name="span_days">
					<option value="1"<?php if ($row['span_days'] == "1") echo " selected=\"selected\""; ?>>1</option>
					<option value="2"<?php if ($row['span_days'] == "2") echo " selected=\"selected\""; ?>>2</option>
					<option value="3"<?php if (!$row['span_days'] || $row['span_days'] == "3") echo " selected=\"selected\""; ?>>3</option>
					<option value="4"<?php if ($row['span_days'] == "4") echo " selected=\"selected\""; ?>>4</option>
					<option value="5"<?php if ($row['span_days'] == "5") echo " selected=\"selected\""; ?>>5</option>
					<option value="6"<?php if ($row['span_days'] == "6") echo " selected=\"selected\""; ?>>6</option>
					<option value="7"<?php if ($row['span_days'] == "7") echo " selected=\"selected\""; ?>>7</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>Target Page:
				<a class="tt" href="#">
				<img height="12" width="12" border="0" src="images/help_icon.gif"/>
				<span class="tooltip">
					<span class="top"></span>
					<span class="middle">Where should the list be written to? For example, "Wikipedia:Foobar/Hot_articles"</span>
					<span class="bottom"></span>
				</span>
				</a>
			</td>
			<td><input name="target_page" value="<?php echo stripslashes($row['target_page']); ?>" type="text" size="40" maxlength="255"/></td>
		</tr>
		<tr>
			<td>Orange Threshold:
				<a class="tt" href="#">
				<img height="12" width="12" border="0" src="images/help_icon.gif"/>
				<span class="tooltip">
					<span class="top"></span>
					<span class="middle">How many edits does an article need to be marked orange?</span>
					<span class="bottom"></span>
				</span>
				</a>
			</td>
			<td>
				<select name="orange">
					<option value="5"<?php if ($row['orange'] == "5") echo " selected=\"selected\""; ?>>5</option>
					<option value="10"<?php if (!$row['orange'] || $row['orange'] == "10") echo " selected=\"selected\""; ?>>10</option>
					<option value="15"<?php if ($row['orange'] == "15") echo " selected=\"selected\""; ?>>15</option>
					<option value="20"<?php if ($row['orange'] == "20") echo " selected=\"selected\""; ?>>20</option>
					<option value="25"<?php if ($row['orange'] == "25") echo " selected=\"selected\""; ?>>25</option>
					<option value="30"<?php if ($row['orange'] == "30") echo " selected=\"selected\""; ?>>30</option>
					<option value="40"<?php if ($row['orange'] == "40") echo " selected=\"selected\""; ?>>40</option>
					<option value="50"<?php if ($row['orange'] == "50") echo " selected=\"selected\""; ?>>50</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>Red Threshold:
				<a class="tt" href="#">
				<img height="12" width="12" border="0" src="images/help_icon.gif"/>
				<span class="tooltip">
					<span class="top"></span>
					<span class="middle">How many edits does an article need to be marked red?</span>
					<span class="bottom"></span>
				</span>
				</a>
			</td>
			<td>
				<select name="red">
					<option value="10"<?php if ($row['red'] == "10") echo " selected=\"selected\""; ?>>10</option>
					<option value="15"<?php if ($row['red'] == "15") echo " selected=\"selected\""; ?>>15</option>
					<option value="20"<?php if (!$row['red'] || $row['red'] == "20") echo " selected=\"selected\""; ?>>20</option>
					<option value="25"<?php if ($row['red'] == "25") echo " selected=\"selected\""; ?>>25</option>
					<option value="30"<?php if ($row['red'] == "30") echo " selected=\"selected\""; ?>>30</option>
					<option value="40"<?php if ($row['red'] == "40") echo " selected=\"selected\""; ?>>40</option>
					<option value="50"<?php if ($row['red'] == "50") echo " selected=\"selected\""; ?>>50</option>
					<option value="60"<?php if ($row['red'] == "60") echo " selected=\"selected\""; ?>>60</option>
					<option value="80"<?php if ($row['red'] == "80") echo " selected=\"selected\""; ?>>80</option>
					<option value="100"<?php if ($row['red'] == "100") echo " selected=\"selected\""; ?>>100</option>
				</select>
			</td>
		</tr></table>
	<p><input type="hidden" name="id" value="<?php echo $_GET['id']; ?>"/><input class="button" type="submit" name="submitted" value="Edit Subscription"/></p>
	<p>[ <a href="configure.php">Return to main page</a> ]</p>
</form>

<?php
include ("footer.inc.php");
?>
