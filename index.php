<?php
/**
 * Provincial School Day Calendar
 *  To provide a subscribable calendar with the school days
 * @author Jared Whiklo
 */

// This includes the math to calculate all the holidays we can.
require('SchoolHolidays.class.php');

/**
 * @var $xmas_breaks array
 *  Associative array of Xmas break dates
 */
$xmas_breaks = array(
  '2012-2013' => array(
    '2012-12-24',
    '2013-01-06',
  ),
  '2013-2014' => array(
    '2013-12-23',
    '2014-01-03',
  ),
  '2014-2015' => array(
    '2014-12-22',
    '2015-01-02',
  ),
  '2015-2016' => array(
    '2015-12-21',
    '2016-01-01',
  ),
  '2016-2017' => array(
    '2016-12-23',
    '2017-01-06',
  ),
  '2017-2018' => array(
    '2017-12-23',
    '2018-01-07',
  ),
);

/**
 * @var $cache_dir string
 *  Directory for
 */
$cache_dir = __DIR__ . '/../tmp';

/**
 * @var $dt DateTime
 *  Current date and time
 */
$dt = new DateTime();
$dt->setTimezone(new DateTimeZone('America/Winnipeg'));

/**
 * @var $modified string
 *  Unix timestamp of when the file was last updated
 */
$modified = $dt->setTimestamp(filemtime(__FILE__))->format('Ymd\THis\Z');

/**
 * @var $school_years array
 *  School Year, from the QUERY_STRING or assume current or upcoming year
 *  Change over on August 1st.
 */
$school_years = array();
if (array_key_exists('year',$_GET) && preg_match('/^([0-9]{4})\-([0-9]{4})$/',$_GET['year'], $year_match)){
  $school_years[] = $year_match[1];
  $school_years[] = $year_match[2];
} else {
  if (date('n',time())>7){
    $school_years[] = date('Y',time());
    $school_years[] = (date('Y',time()) + 1);
  } else {
    $school_years[] = (date('Y',time()) - 1);
    $school_years[] = date('Y',time());
  }
}
/**
 * @var $school_year_print string
 *  printable version of school year for calendar header
 */
$school_year_print = $school_years[0]."-".$school_years[1];


/**
 * @var $school_days array
 *  First and last day of school calculated
 *  as the first Tuesday after Labour Day and last weekday
 *  of June
 */
$school_days = array();
$school_days[] = $dt->setDate($school_years[0],8,31)->modify('next monday')->modify('next tuesday')->format('Y-m-d');
$last_day = $dt->setDate($school_years[1],6,30);
if ($last_day->format('w') == 0 || $last_day->format('w') == 6) {
  $last_day->modify('last friday');
}
$school_days[] = $last_day->format('Y-m-d');


/**
 * @var $cache_file string
 *  The cache file for this year
 */
$cache_file = $cache_dir . '/' . $school_year_print . '.ics';

// If there is no cache file, or the cache file is older than this file.
if (!file_exists($cache_file) || $dt->setTimestamp(filemtime(__FILE__)) > $dt->setTimestamp(filemtime($cache_file))) {
  if (!array_key_exists($school_year_print, $xmas_breaks)) {
    // No Xmas break entered so the dates would be incorrect.
    // Display nothing
    exit;
  }

  /**
   * @var $holidays SchoolHolidays
   *  class determines school holiday dates
   */
  try {
    $holidays = new SchoolHolidays($school_years[0] . "09-01", $xmas_breaks[$school_year_print]);
    $content = printCalendar($holidays, $school_year_print, $school_days, $dt);
    if (is_dir($cache_dir) && is_writable($cache_dir)) {
      file_put_contents($cache_file, $content);
    }
  } catch (\Exception $e) {
    print "Error creating calendar: {$e->getMessage()}";
    exit();
  }

} else {
  $content = file_get_contents($cache_file);
}

Header('Content-type: text/calendar; charset=UTF-8');
//Header('Content-type: text/plain; charset=UTF-8'); // Output as text to debug
print $content;



/**
 * Print the calendar
 *
 * @param $holidays SchoolHolidays
 *  holidays class
 * @param $school_year_print
 *  printable version of the school year
 * @param $school_days array
 *  array of first and last day of school.
 * @param $date DateTime
 *  date time to generate other dates.
 * @return string
 *  The Vcalendar string
 */
function printCalendar($holidays, $school_year_print, $school_days, $date)
{

  /**
   * @var $created string
   *  Unix timestamp of when the file was created
   */
  $created = $date->createFromFormat('Y-m-d G:i:s','2012-10-17 21:50:00')->format('Ymd\THis\Z');

  /**
   * @var $modified string
   *  Unix timestamp of when the file was last updated
   */
  $modified = $date->setTimestamp(filemtime(__FILE__))->format('Ymd\THis\Z');


  try {
    /**
     * @var $counter DateTime
     *  The first date of school
     */
    $counter = new DateTime($school_days[0]);
    /**
     * @var $last_day DateTime
     *  The last date of school
     */
    $last_day = new DateTime($school_days[1]);
  } catch (Exception $e) {
    print "ERROR " . $e->getMessage();
    exit();
  }

  /**
   * @var $calendar string
   *  Holder for the VCALENDAR content
   */
  $calendar = getVcalendarHeader($school_year_print);

  $schoolDay = 1; // School always starts on day 1
  // Loop from the first day to the last day of school using DateTime objects
  while ($counter <= $last_day) {
    // If its not Saturday, Sunday or a holiday
    if ($counter->format('w') < 6 && $counter->format('w') > 0 && (!$holidays->isHoliday($counter))) {
      $calendar .= createDay($created, $modified, $counter, 'School Day ' . $schoolDay); // print the VEVENT
      $schoolDay += 1;
      if ($schoolDay > 6) {
        $schoolDay = 1;
      }
    }
    $counter->modify('+1 day');
  }
  $calendar .= 'END:VCALENDAR';
  return $calendar;
}

/**
 * createDay
 *  Outputs a VEVENT for the school day
 *
 * @param $created string
 *  Created date of calendar
 * @param $modified string
 *  Last modified of calendar
 * @param $date DateTime
 *  the date
 * @param $event string
 *  event summary
 * @return string
 *  the VEVENT
 */
function createDay($created, $modified, $date, $event)
{
  $uid_date = $date->format('Ymd') . 'T1200Z';
  $start = $date->format('Ymd');
  $val = <<<EOF
BEGIN:VEVENT
UID:$uid_date
TRANSP:TRANSPARENT
CREATED:$created
DSTAMP:$modified
SUMMARY:$event
DTSTART;VALUE=DATE:$start
STATUS:CONFIRMED
END:VEVENT

EOF;
  return $val;
}

/**
 * Return a VCALENDAR header
 *
 * @param $school_year string
 *  printable short form school year
 * @return string
 *  The header
 *
 */
function getVcalendarHeader($school_year)
{

  $header = <<<EOF
BEGIN:VCALENDAR
PRODID:-//Tricksey Hobbits//NOSGML Hacksaw//EN
X-WR-TIMEZONE:America/Winnipeg
X-WR-CALDESC:
VERSION:2.0
X-APPLE-CALENDAR-COLOR:#492BA1
X-WR-CALNAME:Manitoba School Days ($school_year)
METHOD:PUBLISH
CALSCALE:GREGORIAN
CLASS:PUBLIC

EOF;
  return $header;
}
