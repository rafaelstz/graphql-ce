<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Framework\Stdlib\DateTime;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ScopeInterface;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Phrase;

/**
 * Timezone library
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Timezone implements TimezoneInterface
{
    /**
     * @var array
     */
    protected $_allowedFormats = [
        \IntlDateFormatter::FULL,
        \IntlDateFormatter::LONG,
        \IntlDateFormatter::MEDIUM,
        \IntlDateFormatter::SHORT,
    ];

    /**
     * @var string
     */
    protected $_scopeType;

    /**
     * @var ScopeResolverInterface
     */
    protected $_scopeResolver;

    /**
     * @var \Magento\Framework\Stdlib\DateTime
     */
    protected $_dateTime;

    /**
     * @var string
     */
    protected $_defaultTimezonePath;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var ResolverInterface
     */
    protected $_localeResolver;

    /**
     * @param ScopeResolverInterface $scopeResolver
     * @param ResolverInterface $localeResolver
     * @param \Magento\Framework\Stdlib\DateTime $dateTime
     * @param ScopeConfigInterface $scopeConfig
     * @param string $scopeType
     * @param string $defaultTimezonePath
     */
    public function __construct(
        ScopeResolverInterface $scopeResolver,
        ResolverInterface $localeResolver,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        ScopeConfigInterface $scopeConfig,
        $scopeType,
        $defaultTimezonePath
    ) {
        $this->_scopeResolver = $scopeResolver;
        $this->_localeResolver = $localeResolver;
        $this->_dateTime = $dateTime;
        $this->_defaultTimezonePath = $defaultTimezonePath;
        $this->_scopeConfig = $scopeConfig;
        $this->_scopeType = $scopeType;
    }

    /**
     * Return path to default timezone
     *
     * @return string
     */
    public function getDefaultTimezonePath()
    {
        return $this->_defaultTimezonePath;
    }

    /**
     * Retrieve timezone code
     *
     * @return string
     */
    public function getDefaultTimezone()
    {
        return 'UTC';
    }

    /**
     * Gets the scope config timezone
     *
     * @param string $scopeType
     * @param string $scopeCode
     * @return string
     */
    public function getConfigTimezone($scopeType = null, $scopeCode = null)
    {
        return $this->_scopeConfig->getValue(
            $this->getDefaultTimezonePath(),
            $scopeType ?: $this->_scopeType,
            $scopeCode
        );
    }

    /**
     * Retrieve ISO date format
     *
     * @param   int $type
     * @return  string
     */
    public function getDateFormat($type = \IntlDateFormatter::SHORT)
    {
        return (new \IntlDateFormatter(
            $this->_localeResolver->getLocale(),
            $type,
            \IntlDateFormatter::NONE
        ))->getPattern();
    }

    /**
     * Retrieve short date format with 4-digit year
     *
     * @return  string
     */
    public function getDateFormatWithLongYear()
    {
        return preg_replace(
            '/(?<!y)yy(?!y)/',
            'Y',
            $this->getDateFormat()
        );
    }

    /**
     * Retrieve ISO time format
     *
     * @param   string $type
     * @return  string
     */
    public function getTimeFormat($type = \IntlDateFormatter::SHORT)
    {
        return (new \IntlDateFormatter(
            $this->_localeResolver->getLocale(),
            \IntlDateFormatter::NONE,
            $type
        ))->getPattern();
    }

    /**
     * Retrieve ISO datetime format
     *
     * @param   string $type
     * @return  string
     */
    public function getDateTimeFormat($type)
    {
        return $this->getDateFormat($type) . ' ' . $this->getTimeFormat($type);
    }

    /**
     * Create \DateTime object for current locale
     *
     * @param mixed $date
     * @param string $locale
     * @param bool $useTimezone
     * @param bool $includeTime
     * @return \DateTime
     */
    public function date($date = null, $locale = null, $useTimezone = true, $includeTime = true)
    {
        $locale = $locale ?: $this->_localeResolver->getLocale();
        $timezone = $useTimezone
            ? $this->getConfigTimezone()
            : date_default_timezone_get();

        switch (true) {
            case (empty($date)):
                return new \DateTime('now', new \DateTimeZone($timezone));
            case ($date instanceof \DateTime):
                return $date->setTimezone(new \DateTimeZone($timezone));
            case ($date instanceof \DateTimeImmutable):
                return new \DateTime($date->format('Y-m-d H:i:s'), $date->getTimezone());
            case (!is_numeric($date)):
                $timeType = $includeTime ? \IntlDateFormatter::SHORT : \IntlDateFormatter::NONE;
                $formatter = new \IntlDateFormatter(
                    $locale,
                    \IntlDateFormatter::SHORT,
                    $timeType,
                    new \DateTimeZone($timezone)
                );

                $date = $this->appendTimeIfNeeded($date, $includeTime);
                $date = $formatter->parse($date) ?: (new \DateTime($date))->getTimestamp();
                break;
        }

        return (new \DateTime(null, new \DateTimeZone($timezone)))->setTimestamp($date);
    }

    /**
     * Create \DateTime object with date converted to scope timezone and scope Locale
     *
     * @param   mixed $scope Information about scope
     * @param   string|integer|\DateTime|array|null $date date in UTC
     * @param   boolean $includeTime flag for including time to date
     * @return  \DateTime
     */
    public function scopeDate($scope = null, $date = null, $includeTime = false)
    {
        $timezone = $this->_scopeConfig->getValue($this->getDefaultTimezonePath(), $this->_scopeType, $scope);
        $date = new \DateTime(is_numeric($date) ? '@' . $date : $date, new \DateTimeZone($timezone));
        if (!$includeTime) {
            $date->setTime(0, 0, 0);
        }
        return $date;
    }

    /**
     * Format date using current locale options and time zone.
     *
     * @param \DateTime|null $date
     * @param int $format
     * @param bool $showTime
     * @return string
     */
    public function formatDate($date = null, $format = \IntlDateFormatter::SHORT, $showTime = false)
    {
        $formatTime = $showTime ? $format : \IntlDateFormatter::NONE;

        if (!($date instanceof \DateTimeInterface)) {
            $date = new \DateTime($date);
        }

        return $this->formatDateTime($date, $format, $formatTime);
    }

    /**
     * Get scope timestamp
     *
     * Timestamp will be built with scope timezone settings
     *
     * @param   mixed $scope
     * @return  int
     */
    public function scopeTimeStamp($scope = null)
    {
        $timezone = $this->_scopeConfig->getValue($this->getDefaultTimezonePath(), $this->_scopeType, $scope);
        $currentTimezone = @date_default_timezone_get();
        @date_default_timezone_set($timezone);
        $date = date('Y-m-d H:i:s');
        @date_default_timezone_set($currentTimezone);
        return strtotime($date);
    }

    /**
     * Checks if current date of the given scope (in the scope timezone) is within the range
     *
     * @param int|string|\Magento\Framework\App\ScopeInterface $scope
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return bool
     */
    public function isScopeDateInInterval($scope, $dateFrom = null, $dateTo = null)
    {
        if (!$scope instanceof ScopeInterface) {
            $scope = $this->_scopeResolver->getScope($scope);
        }

        $scopeTimeStamp = $this->scopeTimeStamp($scope);
        $fromTimeStamp = strtotime($dateFrom);
        $toTimeStamp = strtotime($dateTo);
        if ($dateTo) {
            // fix date YYYY-MM-DD 00:00:00 to YYYY-MM-DD 23:59:59
            $toTimeStamp += 86400;
        }

        $result = false;
        if (!$this->_dateTime->isEmptyDate($dateFrom) && $scopeTimeStamp < $fromTimeStamp) {
        } elseif (!$this->_dateTime->isEmptyDate($dateTo) && $scopeTimeStamp > $toTimeStamp) {
        } else {
            $result = true;
        }
        return $result;
    }

    /**
     * @param string|\DateTimeInterface $date
     * @param int $dateType
     * @param int $timeType
     * @param string|null $locale
     * @param string|null $timezone
     * @param string|null $pattern
     * @return string
     */
    public function formatDateTime(
        $date,
        $dateType = \IntlDateFormatter::SHORT,
        $timeType = \IntlDateFormatter::SHORT,
        $locale = null,
        $timezone = null,
        $pattern = null
    ) {
        if (!($date instanceof \DateTimeInterface)) {
            $date = new \DateTime($date);
        }

        if ($timezone === null) {
            if ($date->getTimezone() == null || $date->getTimezone()->getName() == 'UTC'
                || $date->getTimezone()->getName() == '+00:00'
            ) {
                $timezone = $this->getConfigTimezone();
            } else {
                $timezone = $date->getTimezone();
            }
        }

        $formatter = new \IntlDateFormatter(
            $locale ?: $this->_localeResolver->getLocale(),
            $dateType,
            $timeType,
            $timezone,
            null,
            $pattern
        );
        return $formatter->format($date);
    }

    /**
     * Convert date from config timezone to Utc.
     *
     * If pass \DateTime object as argument be sure that timezone is the same with config timezone
     *
     * @param string|\DateTimeInterface $date
     * @param string $format
     * @throws LocalizedException
     * @return string
     * @deprecated
     */
    public function convertConfigTimeToUtc($date, $format = 'Y-m-d H:i:s')
    {
        return $this->convertConfigTimeToUtcWithPattern($date, $format);
    }

    /**
     * Convert date from config timezone to Utc.
     *
     * If pass \DateTime object as argument be sure that timezone is the same with config timezone
     *
     * @param string|\DateTimeInterface $date
     * @param string $format
     * @param string $pattern
     * @throws LocalizedException
     * @return string
     * @deprecated
     */
    public function convertConfigTimeToUtcWithPattern($date, $format = 'Y-m-d H:i:s', $pattern = null)
    {
        if (!($date instanceof \DateTimeInterface)) {
            if ($date instanceof \DateTimeImmutable) {
                $date = new \DateTime($date->format('Y-m-d H:i:s'), new \DateTimeZone($this->getConfigTimezone()));
            } else {
                $locale = $this->_localeResolver->getLocale();
                if ($locale === null) {
                    $pattern = 'Y-M-dd HH:mm:ss';
                }
                $formatter = new \IntlDateFormatter(
                    $locale,
                    \IntlDateFormatter::MEDIUM,
                    \IntlDateFormatter::MEDIUM,
                    $this->getConfigTimezone(),
                    null,
                    $pattern
                );
                $unixTime = $formatter->parse($date);
                $dateTime = new DateTime($this);

                $dateUniversal = $dateTime->gmtDate(null, $unixTime);
                $date = new \DateTime($dateUniversal, new \DateTimeZone($this->getConfigTimezone()));
            }
        } else {
            if ($date->getTimezone()->getName() !== $this->getConfigTimezone()) {
                throw new LocalizedException(
                    new Phrase(
                        'The DateTime object timezone needs to be the same as the "%1" timezone in config.',
                        $this->getConfigTimezone()
                    )
                );
            }
        }

        $date->setTimezone(new \DateTimeZone('UTC'));

        return $date->format($format);
    }


    /**
     * Retrieve date with time
     *
     * @param string $date
     * @param bool $includeTime
     * @return string
     */
    private function appendTimeIfNeeded($date, $includeTime)
    {
        if ($includeTime && !preg_match('/\d{1}:\d{2}/', $date)) {
            $date .= " 0:00am";
        }
        return $date;
    }
}
