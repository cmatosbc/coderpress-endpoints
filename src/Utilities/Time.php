<?php

namespace CoderPress\Utilities;

class Time
{
	/**
	 * Calculates the time difference in seconds between the current time and the specified DateTime object, considering the provided timezone.
	 *
	 * @param \DateTime $dateTime The DateTime object to compare with.
	 * @param string $timezone The timezone name to use for the comparison. If null, the local timezone is used.
	 * @return int The time difference in seconds.
	 */
	public static function getTimeDiff(\DateTime $dateTime, string $timezone = null) {
	    $now = new \DateTime();
	    if ($timezone !== null) {
	        $dateTime->setTimezone(new \DateTimeZone($timezone));
	    }

	    $interval = $now->diff($dateTime);
	    $seconds = $interval->h * 3600 + $interval->i * 60 + $interval->s;
	    return $seconds;
	}

	/**
	 * Gets the expiration time for a given interval.
	 *
	 * @param \DateInterval|int $interval The interval to add to the current time.
	 * @return string The expiration time in Y-m-d H:i:s format.
	 */
	public static function getExpireTime(\DateInterval|int $interval)
	{
		$now = new \DateTime();

		if (is_numeric($interval)) {
			$interval = \DateInterval::createFromDateString($interval . ' seconds');
		}

		$futureDate = $now->add($interval);

		return $now->format('Y-m-d H:i:s');
	}
}