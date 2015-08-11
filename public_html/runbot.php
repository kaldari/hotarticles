<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set('max_execution_time', 2000);

/* Exit if not run from command-line. */
if  ( php_sapi_name() !== 'cli' ) {
	echo "This script should be run from the command-line.";
	die();
}

/* Setup the mediawiki classes. */
require_once dirname(__FILE__) . '/../config.inc.php';
require_once dirname(__FILE__) . '/../botclasses.php';

function getEditCounts( $link, $source, $days = 3, $limit = 5, $method = 'category' ) {
	$pages = array();
	if ( $days <= 30 ) {
		// Retrieve the ID and timestamp of the first revision within the requested time period.
		$result = mysqli_query($link, "select s.rev_id,s.rev_timestamp from revision as s where s.rev_timestamp> DATE_FORMAT(DATE_SUB(NOW(),INTERVAL " . $days . " DAY),'%Y%m%d%H%i%s') order by s.rev_timestamp asc limit 1;");
		while ( $row = mysqli_fetch_array( $result ) ) {
			$revId = $row['rev_id'];
			$revTimestamp = $row['rev_timestamp'];
		}
		// Retrieve the pages with the most revisions since the threshold revision.
		if ( $revId && $revTimestamp ) {
			if ( $method === 'template' ) {
				$subquery = "select a.page_id,a.page_title from templatelinks join page as t on t.page_id=tl_from and t.page_namespace=1 join page as a on a.page_title=t.page_title and a.page_namespace=0 where tl_title='".$source."' and a.page_latest>".$revId;
			} else {
				$subquery = "select a.page_id,a.page_title from categorylinks join page as t on t.page_id=cl_from and t.page_namespace=1 join page as a on a.page_title=t.page_title and a.page_namespace=0 where cl_to='".$source."' and a.page_latest>".$revId;
			}
			$result = mysqli_query($link, "select main.page_title as title,count(main.rc_minor) as ctall, sum(main.rc_minor) from (select tt.page_title,rc_minor,rc_user_text from recentchanges join (".$subquery.") as tt on rc_cur_id=tt.page_id where rc_timestamp>".$revTimestamp.") as main group by main.page_title order by ctall desc limit ".$limit.";");
			while ( $row = mysqli_fetch_array( $result ) ) {
				$title = str_replace( '_', ' ', $row['title'] );
				$pages[$title] = $row['ctall'];
			}
		}
	}
	return $pages;
}

$wikipedia = new wikipedia();

/* Log in to wikipedia. */
$wikipedia->login( $enwiki['user'], $enwiki['pass'] );

$link = mysqli_connect($hotarticlesdb['host'], $hotarticlesdb['user'], $hotarticlesdb['pass'], $hotarticlesdb['dbname']);

if ( isset( $argv[1] ) && !is_nan( $argv[1] ) ) {
	$argv[1] = mysqli_real_escape_string( $link, $argv[1] );
	$query = "SELECT * FROM hotarticles WHERE id = $argv[1]";
} else {
	$query = "SELECT * FROM hotarticles";
}
$result = mysqli_query($link, $query) or die(mysqli_error());

$link = mysqli_connect($enwikidb['host'], $enwikidb['user'], $enwikidb['pass'], $enwikidb['dbname']);

while ($row = mysqli_fetch_array ($result)) {
	if ($row['method'] == 'category') {
		$category = str_replace(' ', '_', $row['source']);
		$count = $wikipedia->categorypagecount('Category:'.$category);
		if ($count < $maxArticles) {
			$editCounts = getEditCounts( $link, $category, $row['span_days'], $row['article_number'], $row['method'] );
		} else {
			echo "Category ".$row['source']." is too large. Skipping.\n";
			continue;
		}
	} else if ($row['method'] == 'template') {
		$template = str_replace(' ', '_', $row['source']);
		$editCounts = getEditCounts( $link, $template, $row['span_days'], $row['article_number'], $row['method'] );
	} else {
		echo "Invalid method for ".$row['source'].". Skipping.\n";
		continue;
	}

	echo $row['source']."\n";
	$output = "{|\n";
	$validUpdate = false;
	$output = "{|\n";
	$validUpdate = false;
	foreach ( $editCounts as $key => $value ) {
		if ( $value != '' && $key != '' ) {
			$validUpdate = true;
			switch ( true ) {
				case ( $value >= $row['red'] ):
					$color = '#c60d27';
					break;
				case ( $value >= $row['orange'] ):
					$color = '#f75a0d';
					break;
				case ( $value > 0 ):
					$color = '#ff9900';
					break;
			}
			$output .= <<<WIKITEXT
|-
| style="text-align:center; font-size:130%; color:white; background:$color; padding: 0 0.2em" | '''$value'''&nbsp;<span style="font-size:60%">edits</span>
| style="padding: 0.4em;" | [[$key]]
WIKITEXT;
			$output .= "\n";
		}
	}
	$output .= <<<WIKITEXT
|-
| style="padding: 0.1em;" |
|}
WIKITEXT;
	$wordarray = array('zero','one','two','three','four','five','six','seven','eight','nine','ten');
	$date = date('j F Y', time());
	$output .= "\nThese are the articles that have been edited the most within the last ".$wordarray[$row['span_days']]." days. Last updated $date.\n";

	if ( $validUpdate ) {
		$edit = $wikipedia->edit($row['target_page'],$output,'Updating for '.date('F j, Y'));
		//if ($edit) echo "Completed update for ".$row['source'].".\n";
	}
}
echo "$date: Bot run";
