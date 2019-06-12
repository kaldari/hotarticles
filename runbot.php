<?php
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'max_execution_time', 600 );

// Exit if not run from command-line
if  ( php_sapi_name() !== 'cli' ) {
	echo "This script should be run from the command-line.";
	die();
}

// Set up configuration and wikipedia class
require_once dirname(__FILE__) . '/config.inc.php';
require_once dirname(__FILE__) . '/botclasses.php';

function getEditCounts( $link, $source, $days = 3, $limit = 5, $method = 'category' ) {
	$pages = array();
	if ( $days <= 30 ) {
		// Retrieve the ID and timestamp of the first revision within the requested time period.
		$result = mysqli_query($link, "select s.rev_id,s.rev_timestamp from revision as s where s.rev_timestamp> DATE_FORMAT(DATE_SUB(NOW(),INTERVAL " . $days . " DAY),'%Y%m%d%H%i%s') order by s.rev_timestamp asc limit 1;");
		if ( $result ) {
			while ( $row = mysqli_fetch_array( $result ) ) {
				$revId = $row['rev_id'];
				$revTimestamp = $row['rev_timestamp'];
			}
			// Retrieve the pages with the most revisions since the threshold revision.
			if ( $revId && $revTimestamp ) {
				$source = mysqli_real_escape_string( $link, $source );
				if ( $method === 'template' ) {
					$subquery = "select a.page_id,a.page_title from templatelinks join page as t on t.page_id=tl_from and t.page_namespace=1 join page as a on a.page_title=t.page_title and a.page_namespace=0 where tl_title='".$source."' and a.page_latest>".$revId;
				} else {
					$subquery = "select a.page_id,a.page_title from categorylinks join page as t on t.page_id=cl_from and t.page_namespace=1 join page as a on a.page_title=t.page_title and a.page_namespace=0 where cl_to='".$source."' and a.page_latest>".$revId;
				}
				$query = "select main.page_title as title,count(main.rc_minor) as ctall, sum(main.rc_minor) from (select tt.page_title,rc_minor from recentchanges join (".$subquery.") as tt on rc_cur_id=tt.page_id where rc_timestamp>".$revTimestamp." and rc_type<2) as main group by main.page_title order by ctall desc limit ".$limit.";";
				$result = mysqli_query( $link, $query );
				if ( $result ) {
					while ( $row = mysqli_fetch_array( $result ) ) {
						$title = str_replace( '_', ' ', $row['title'] );
						$pages[$title] = $row['ctall'];
					}
				} else {
					echo "No pages retrieved. " . mysqli_error( $link ) . "\n";
				}
			}
		} else {
			echo "Could not retrieve ID and timestamp of first revision.\n";
		}
	}
	return $pages;
}

/**
 * Fetch all the subscription data for the bot
 *
 * @param object $wikipedia The interface object for Wikipedia
 * @param string $project The name of a specific WikiProject (e.g. "WikiProject
 *     Spiders"). If no project is specified, config data for all the projects
 *     will be returned.
 * @return array The configuration data
 * @throws Exception
 */
function getSubscriptions( $wikipedia, $project = null ) {
	$configPage = 'User:HotArticlesBot/Config.json';
	$page = $wikipedia->getpage( $configPage );
	if ( $page ) {
		$res = json_decode( $page, true );
		$config = [];
		foreach ( $res as $key => $values ) {
			// Skip the description of the config page format
			if ( $key === 'description' ) {
				continue;
			}
			// If a specific project was requested, skip other projects
			if ( $project && $key !== $project ) {
				continue;
			}
			$config[$key] = [
				'category' => $values['Category'],
				'page' => $values['Page'],
				'articles' => $values['Articles'],
				'days' => $values['Days'],
				'orange' => $values['Orange'],
				'red' => $values['Red']
			];
		}
		return $config;
	} else {
		throw new Exception( 'Could not retrieve config page.' );
	}
}

function isSubscriptionSane( $subscription ) {
	if ( strpos( $subscription['page'], 'Wikipedia:' ) === 0 &&
		$subscription['articles'] >= 5 &&
		$subscription['articles'] <= 10 &&
		$subscription['days'] >= 1 &&
		$subscription['days'] <= 7 &&
		$subscription['orange'] > 0 &&
		$subscription['red'] > 0
	) {
		return true;
	} else {
		return false;
	}
}

$wikipedia = new wikipedia();

// Log in to wikipedia
$wikipedia->login( $enwiki['user'], $enwiki['pass'] );

if ( isset( $argv[1] ) ) {
	$subscriptions = getSubscriptions( $wikipedia, $argv[1] );
} else {
	$subscriptions = getSubscriptions( $wikipedia );
}

$link = mysqli_connect($enwikidb['host'], $enwikidb['user'], $enwikidb['pass'], $enwikidb['dbname']);

// Fetch all the subscriptions and generate a chart for each
foreach ( $subscriptions as $subscriptionName => $row ) {
	if ( !isSubscriptionSane( $row ) ) {
		echo "Subscription for ".$subscriptionName." is malformed. Skipping.\n";
		continue;
	}
	$time_start = microtime(true);

	// Allow up to 5 minutes for each chart to be generated. This resets max_execution_time.
	set_time_limit( 300 );

	$category = str_replace( ' ', '_', $row['category'] );
	$count = $wikipedia->categorypagecount( 'Category:'.$category );
	if ( $count < $maxArticles ) {
		$editCounts = getEditCounts( $link, $category, $row['days'], $row['articles'] );
	} else {
		echo "Category ".$row['category']." is too large. Skipping.\n";
		continue;
	}

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
| style="text-align:center; font-size:130%; color:white; background:$color; padding:0 0.2em; vertical-align:middle;" | '''$value'''&nbsp;<span style="font-size:60%">edits</span>
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
	$output .= "\n<small>These are the articles that have been edited the most within the last ".$wordarray[$row['days']]." days. Last updated $date by [[User:HotArticlesBot|HotArticlesBot]].</small>\n";

	if ( $validUpdate ) {
		$edit = $wikipedia->edit($row['page'],$output,'Updating for '.date('F j, Y'));
	}
	$time_end = microtime(true);
	$execution_time = round( $time_end - $time_start, 2 );
	echo $subscriptionName . " (" . $execution_time . " seconds)\n";
}
echo "$date: Bot run\n";
