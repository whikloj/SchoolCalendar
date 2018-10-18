<?php
/**
 * Class to calculate standarized school holidays in Manitoba.
 * Uses DateTime objects instead of strtotime to avoid 2038 bug
 *
 * This does NOT do Christmas break as that is somewhat arbitrary.
 */

class SchoolHolidays {

  private $today = NULL; // DateTime to calculate holidays using.
  private $timezone = NULL; // DateTimeZone object.
  private $years = NULL; // School year as array of two elements.
  private $holidays = NULL; // Array of holidays, needs the school years to calculate, so is populated in the constructor.
  private $skip_days = NULL; // Array of the days to skip, (ie. expands a range to count for each day).

  /**
   * Constructor
   *
   * @param $today string
   *   Date of the format YYYY-MM-DD or NULL for today.
   * @param $xmas_range mixed
   *   Array of the first and last days of xmas break (YYYY-MM-DD)
   * @param $timezone string
   *   Timezone string for DateTime functions, should be the same as used
   *   in calling script.
   *
   * @throws \Exception
   *   When DateInterval is invalid.
   */
  public function __construct($today=NULL, $xmas_range=array(), $timezone='America/Winnipeg') {
    $this->timezone = new DateTimeZone($timezone);
    $this->today = new DateTime($today, $this->timezone);

    // After July 31st, do this year and next year
    if ($this->today->format('n') > 7){
      $this->years = array(
        $this->today->format('Y'),
        $this->today->add(new DateInterval('P1Y'))->format('Y'),
      );
    }
    // otherwise we are between Jan and July, so do this year and last year
    else {
      $this->years = array(
        $this->today->sub(new DateInterval('P1Y'))->format('Y'),
        $this->today->format('Y'),
      );
    }
    $this->calculateHolidays();
    if (count($xmas_range) > 0){
      // Add in the range for Xmas break
      $this->holidays[] = $xmas_range;
    }
    // Convert ranges to dates
    $this->getSkipDays();
  }

  /**
   * Checks the date to see if it is a holiday
   * @param $date - string date to check
   * @return boolean - TRUE if a holiday, FALSE otherwise
   */
  public function isHoliday($date){
    if ($date instanceof DateTime){
      $tmpD = $date->format('Y-m-d');
    } else if (is_int($date)){
      // Incase someone sends a Unix timestamp, we'll assume integers are already converted
      $tmpD = date('Y-m-d',$date);
    } else {
      // Otherwise try to create a DateTime from the string.
      $dt = new DateTime($date, $this->timezone);
      $tmpD = $dt->format('Y-m-d');
    }
    return in_array($tmpD, $this->skip_days);
  }

  /**
   * Get a copy of the skip_days array, to use elsewhere.
   *
   * @return mixed
   *   array of timestamps for days that are holidays
   */
  public function getHolidaysArray(){
    return $this->skip_days;
  }

  /**
   * Print the holiday array to see what we are skipping.
   *
   * @param $html boolean
   *   Print as HTML or text
   */
  public function printHolidays($html=FALSE){
    foreach ($this->holidays as $key => $hol){
      if ($html) {
        print "<p>";
      }
      print "$key => ";
      if (is_array($hol)){
        print "(" . $hol[0].") to (" . $hol[1]. ")\n";
      } else {
        print $hol."\n";
      }
      if ($html) {
        print "</p>";
      }
    }
  }

  /**
   * For standardized holidays this figures out the days for the year
   */
  private function calculateHolidays(){
    $dt = new DateTime(NULL, $this->timezone);

    $this->holidays = array(
      // Canadian Thanksgiving is the second Monday in October
      'Thanksgiving' => $dt->setDate($this->years[0],10,01)->modify('second monday of')->format('Y-m-d'),
      // Remembrance Day (Veterans Day) is November 11th
      'Remembrance Day' => $this->years[0]."-11-11",
      // Louis Riel Day (Manitoba, Canada) is the third Monday of February
      'Louis Riel Day' => $dt->setDate($this->years[1],02,01)->modify('third monday of')->format('Y-m-d'),
      // Good Friday is the friday before Easter
      'Good Friday' => $this->calculateEaster($this->years[1])->modify('previous Friday')->format('Y-m-d'),
      // Victoria Day is the Monday on or before May 24th
      'Victoria Day' => $dt->setDate($this->years[1],5,25)->modify('previous monday')->format('Y-m-d'),
    );
    // Spring Break is the last week of March that contains at least one weekday in March.
    if ( $dt->setDate($this->years[1],03,31)->format('w') == 1){
      $this->holidays['Spring Break'] = array(
        $dt->format('Y-m-d'),
        $dt->modify('next friday')->format('Y-m-d'),
      );
    } else {
      $this->holidays['Spring Break'] = array(
        $dt->modify('previous monday')->format('Y-m-d'),
        $dt->modify('next friday')->format('Y-m-d'),
      );
    }
  }

  /**
   * Converts the range of days from the holidays into an array of dates with no school
   */
  private function getSkipDays(){
    foreach ($this->holidays as $hdays){
      if (!is_array($hdays)){
        // Single day
        $this->skip_days[] = $hdays;
      } else if (is_array($hdays)){
        // Range of days
        $tmpS = new DateTime($hdays[0]);
        $tmpE = new DateTime($hdays[1]);
        while ($tmpS <= $tmpE){
          $this->skip_days[] = $tmpS->format('Y-m-d');
          $tmpS->modify('+1 day');
        }
      }
    }
  }

  /**
   * Calculates Easter Sunday given a year.
   * Based on http://aa.usno.navy.mil/faq/docs/easter.php
   * @param $y
   *   The year to calculate Easter for
   * @return DateTime
   *   Unix timestamp of Easter Sunday
   */
  private function calculateEaster($y){
    $c = intval($y / 100);
    $n = $y - intval(19 * intval($y / 19));
    $k = (int)($c - 17 ) / 25;
    $i = $c - intval($c / 4) - intval(( $c - $k ) / 3) + intval(19 * $n) + 15;
    $i = $i - intval(30 * intval( $i / 30 ));
    $i = $i - intval(intval( $i / 28 ) * intval(1 - intval( $i / 28 ) * intval( 29 / ( $i + 1 )) * intval(( 21 - $n ) / 11 )));
    $j = $y + intval($y / 4) + $i + 2 - $c + intval($c / 4);
    $j = $j - intval(7 * intval( $j / 7 ));
    $l = $i - $j;
    $m = 3 + intval(intval($l + 40 ) / 44);
    $d = $l + 28 - intval(31 * intval( $m / 4 ));
    return(new DateTime("$y-$m-$d"));
  }
}
