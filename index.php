<?php
header('Content-Type: application/rss+xml; charset=utf-8');
/**
*	@author https://www.apptha.com/blog/import-google-calendar-events-in-php/
*	@author MDY
*	@date 2018-08-16
*/

$rssName = "gera.ratsinfomanagement.net.rss";
$now = date_create('now');
if (file_exists($rssName)) {
	$fdate = date_create("@".filemtime($rssName));
	$fdate->modify('+1 hour');
	if($now<$fdate) {
	// if($now<$fdate && false) {
		//	use old
		echo file_get_contents($rssName);
		die();
	} else {
		//	new
	}
}

$file = "https://gera.ratsinfomanagement.net/termine/ics/SD.NET_RIM_4.ics";
// $file = "SD.NET_RIM_4.ics";
$obj = new ics($file);
$sessions = $obj->getSessionsInRange(10,20);
$categories = ics::getCategories($sessions);

$rss = fopen($rssName, "w+");
$output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<rss version=\"2.0\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\" xmlns:wfw=\"http://wellformedweb.org/CommentAPI/\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:atom=\"http://www.w3.org/2005/Atom\" xmlns:sy=\"http://purl.org/rss/1.0/modules/syndication/\" xmlns:slash=\"http://purl.org/rss/1.0/modules/slash/\">
<channel>
	<title>gera.ratsinfomanagement.net</title>
	<atom:link href=\"http://10.1.43.30/sdnet2rss/\" rel=\"self\" type=\"application/rss+xml\" />
	<link>http://10.1.43.30/sdnet2rss/</link>
	<description></description>
	<lastBuildDate>Thu, 16 Aug 2018 12:29:16 +0000</lastBuildDate>
	<language>de-DE</language>
	<sy:updatePeriod>hourly</sy:updatePeriod>
	<sy:updateFrequency>1</sy:updateFrequency>
	<generator>MDY</generator>
";
foreach($sessions as $s) {
	$output.="<item>
		<title>".$s->category." (".$s->title.")</title>
		<link>".$s->src."</link>
		<pubDate>".$s->start->format(DateTime::RSS)."</pubDate>
		<category><![CDATA[".$s->category."]]></category>
		<guid isPermaLink=\"false\">".$s->src."</guid>
		<description><![CDATA[".$s->category."]]></description>
		<content:encoded><![CDATA[".$s->content."]]></content:encoded>
	</item>";
}
$output .= "</channel>";
$output .= "</rss>";

fwrite($rss, $output);
fclose($rss);


class ics {
	public $src;
	
	public function __construct($src) {
		$this->src = $src;
	}
	public function getSessionsInRange($daysBackward, $daysForward) {
		$data = $this->getIcsEventsAsArray($this->src);
		$now = date_create('now');
		$items = array();
		foreach($data as $d) {
			if($d["BEGIN"]=="VEVENT" && isset($d["DTSTART"])) {
				$start = date_create($d["DTSTART"]);
				$dd = date_diff($now, $start);
				if(($now>$start && $dd->days<$daysBackward) || ($now<=$start && $dd->days<$daysForward)) {
					$item = new stdClass;
					$item->start	= new DateTime($d["DTSTART"]);
					$item->end		= new DateTime($d["DTEND"]);
					$item->category	= $d["SUMMARY"];
					$item->src		= substr($d["DESCRIPTION"], strpos($d["DESCRIPTION"], "http"));
					$item->location	= $d["LOCATION"];
					$sdata			= self::getSessionData($item->src);
					$item->content	= $sdata->content;
					$item->title	= isset($sdata->title) ? $sdata->title : $item->category;
					$items[] 		= $item;
					// break;
				}
			}
		}
		return $items;
	}
	
	public static function getSessionData($url) {
		$return = new stdClass;
		$c = file_get_contents($url);
		$p1 = strpos($c, '<div id="div-content" class="tops">');
		$p2 = strpos($c, '</div><!-- end content -->', $p1)+6;
		$return->content = substr($c, $p1, $p2-$p1);

		$pattern = "/\<td>\<a href=\"\/gremien.*\<\/a>, (.*)\<\/td>/im";
		preg_match($pattern, $c, $matches, PREG_OFFSET_CAPTURE);
		$return->title = isset($matches[1][0]) ? $matches[1][0] : NULL;
		return $return;
	}
	
	public static function getCategories($items) {
		$c = array();
		foreach($items as $i) {
			$c[] = $i->category;
		}
		return array_unique($c);
	}

    /* Function is to get all the contents from ics and explode all the datas according to the events and its sections */
    public function getIcsEventsAsArray($file) {
        $icalString = file_get_contents ( $file );
        $icsDates = array ();
        /* Explode the ICs Data to get datas as array according to string ‘BEGIN:’ */
        $icsData = explode ( "BEGIN:", $icalString );
        /* Iterating the icsData value to make all the start end dates as sub array */
        foreach ( $icsData as $key => $value ) {
            $icsDatesMeta [$key] = explode ( "\n", $value );
        }
        /* Itearting the Ics Meta Value */
        foreach ( $icsDatesMeta as $key => $value ) {
            foreach ( $value as $subKey => $subValue ) {
                /* to get ics events in proper order */
                $icsDates = $this->getICSDates ( $key, $subKey, $subValue, $icsDates );
            }
        }
        return $icsDates;
    }

    /* function is to avoid the elements wich is not having the proper start, end  and summary informations */
	public static function getICSDates($key, $subKey, $subValue, $icsDates) {
        if ($key != 0 && $subKey == 0) {
            $icsDates [$key] ["BEGIN"] = $subValue;
        } else {
            $subValueArr = explode ( ":", $subValue, 2 );
            if (isset ( $subValueArr [1] )) {
                $icsDates [$key] [$subValueArr [0]] = $subValueArr [1];
            }
        }
        return $icsDates;
    }
}
?>