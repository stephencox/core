<?php

/*
 * Copyright (C) 2026 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Base\Validators\CallbackValidator;

class ScheduleListField extends TextField
{
    private bool $internalAsList = false;
    private string $internalFieldSeparator = ',';

    protected function defaultValidationMessage()
    {
        return match ($this->getInternalXMLTagName()) {
            'day' => gettext('Enter a comma-separated list of days from 1 to 31.'),
            'month' => gettext('Enter a comma-separated list of months from 1 to 12.'),
            'hour' => gettext('Enter a time range such as 8:00-17:00.'),
            default => parent::defaultValidationMessage()
        };
    }

    public function getValidators()
    {
        $validators = parent::getValidators();
        $validators[] = new CallbackValidator(['callback' => function ($value) {
            $field = $this->getInternalXMLTagName();
            // XXX: Preserve missing/empty hours: the legacy runtime treats them as all-day ranges.
            // The day field also validates the range's selection mode, even when its own value is empty.
            if ($value === '' && $field !== 'day') {
                return [];
            }
            // Month and Day
            if (($field === 'month' || $field === 'day') && $value !== '') {
                $maximum = $field === 'month' ? 12 : 31;
                foreach (explode(',', $value) as $entry) {
                    // XXX: Preserve zero-padded month/day values such as 02/01 without rewriting them.
                    if (!ctype_digit($entry) || strlen($entry) > 2 || (int)$entry < 1 || (int)$entry > $maximum) {
                        return [$this->getValidationMessage()];
                    }
                }
            // Hours
            } elseif ($field === 'hour') {
                $times = explode('-', $value);
                if (count($times) !== 2) {
                    return [$this->getValidationMessage()];
                }
                $minutes = [];
                foreach ($times as $index => $time) {
                    $parts = explode(':', $time);
                    if (count($parts) !== 2) {
                        return [$this->getValidationMessage()];
                    }
                    // XXX: Preserve unpadded times such as 0:0-24:0 and 8:00-17:15; do not rewrite stored strings.
                    foreach ($parts as $part) {
                        if (!ctype_digit($part) || strlen($part) > 2) {
                            return [$this->getValidationMessage()];
                        }
                    }
                    [$hour, $minute] = array_map('intval', $parts);
                    // XXX: Accept 24:0 and 24:00 only as the stop time, representing midnight at the end of the day.
                    if ($hour > ($index === 1 ? 24 : 23) || $minute > 59 || ($hour === 24 && $minute !== 0)) {
                        return [$this->getValidationMessage()];
                    }
                    $minutes[] = $hour * 60 + $minute;
                }
                // XXX: Preserve equal endpoints such as 9:00-9:00: the legacy UI accepts inactive intervals.
                // XXX: Review the legacy UI restriction against overnight ranges (start > stop).
                if ($minutes[0] > $minutes[1]) {
                    return [gettext('The start time must be no later than the stop time.')];
                }
            }
            if ($field === 'day') {
                $range = $this->getParentNode();
                if (!$range->position->isEmpty()) {
                    if (!$range->month->isEmpty() || !$this->isEmpty()) {
                        return [gettext('Select either weekdays or calendar dates.')];
                    }
                    return [];
                }
                $months = $range->month->getValues();
                $days = $this->getValues();
                $message = gettext('Provide matching month and day lists containing valid calendar dates.');
                if (count($days) === 0 || count($months) !== count($days)) {
                    return [$message];
                }
                foreach ($months as $index => $month) {
                    // XXX: Preserve month=2, day=29 even in non-leap years; legacy dates have no stored year.
                    if (!checkdate((int)$month, (int)$days[$index], 2000)) {
                        return [$message];
                    }
                }
            }
            return [];
        }]);
        return $validators;
    }

    public function isFieldChanged()
    {
        if ($this->getInternalXMLTagName() === 'day' && $this->getParentNode() !== null) {
            // Revalidate paired dates when only the month list or weekday selection changes.
            $range = $this->getParentNode();
            return parent::isFieldChanged() || $range->month->isFieldChanged() || $range->position->isFieldChanged();
        }
        return parent::isFieldChanged();
    }

    public function setAsList($value)
    {
        $this->internalAsList = strtoupper(trim($value)) === 'Y';
    }

    public function setFieldSeparator($value)
    {
        $this->internalFieldSeparator = $value;
    }

    public function getValues(): array
    {
        if (!$this->internalAsList) {
            return parent::getValues();
        }
        // XXX: Keep order and duplicates: month=12,12,1 and day=24,25,1 pair by index, not as independent sets.
        return $this->isSet() ? explode($this->internalFieldSeparator, $this->getValue()) : [];
    }

    public function setValues(array $values)
    {
        if ($this->internalAsList) {
            $this->setValue(implode($this->internalFieldSeparator, $values));
        } else {
            parent::setValues($values);
        }
    }
}
