<?php
require_once dirname(__FILE__) . '/../../wiki_db_link.inc.php';
mysql_select_db("enwiki_p", $wikilink);

function getOffset( $days ) {
	$days = intval( $days );
	$seconds = 86400 * $days;
	$offsetTime = time() - $seconds;
	$offsetTime = strftime( '%G%m%d%H%M%S', $offsetTime );
	return $offsetTime;
}

function getOffsetRev( $offset ) {
	$query = "select rev_id from revision where rev_timestamp > $offset order by rev_timestamp asc limit 1;";
	$result = mysql_query( $query ) or die( mysql_error() );
	$offset = mysql_fetch_array( $result );
	return $offset['rev_id'];
}

function getPages( $offset, $title, $template = false ) {
	$pages = array();
	$title = str_replace( ' ', '_', $title );
	$title = addslashes( $title );

	// Get the pages
	if ( $template ) {
		$query = "SELECT x.page_id, x.page_title FROM templatelinks JOIN page AS y ON y.page_id = tl_from AND y.page_namespace = 1 JOIN page AS x ON x.page_title = y.page_title AND x.page_namespace = 0 WHERE tl_namespace = 10 AND tl_title = \"$title\" AND x.page_touched > $offset";
	} else {
		$query = "SELECT x.page_id, x.page_title FROM categorylinks JOIN page AS y ON y.page_id = cl_from AND y.page_namespace = 1 JOIN page AS x ON x.page_title = y.page_title AND x.page_namespace = 0 WHERE cl_to = \"$title\" AND x.page_touched > $offset";
	}
	$result = mysql_query( $query ) or die( mysql_error() );
	while ( $row = mysql_fetch_array( $result ) ) {
		$pages[$row['page_id']] = $row['page_title'];
	}
	return $pages;
}

$title = "WikiProject Feminism articles";
$offset = getOffset( 3 );
$pages = getPages( $offset, $title, false );
$edits = array();

foreach ( $pages as $id => $page ) {
	$query = "select count(*) as edits from revision where rev_page = $id AND rev_timestamp > $offset;";
    $result = mysql_query( $query ) or die( mysql_error() );
    $row = mysql_fetch_array( $result );
    $edits[$id] = $row['edits'];
}
array_multisort( $edits, SORT_DESC, $pages );
$pages = array_slice( $pages, 0, 10 );
foreach ( $pages as $id => $page ) {
	echo $page.": ".$edits[$id];
	echo "<br/>";
}
