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
    private string $internalFieldSeparator = ',';

    /**
     * Return form options for one endpoint of the legacy hour interval.
     */
    public function getTimeOptions(int $index): array
    {
        $time = $this->getValues()[$index] ?? ($index === 0 ? '00:00' : '23:59');
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');
        $selected = sprintf('%02d:%02d', (int)$hour, (int)$minute);
        $options = [];
        for ($hour = 0; $hour < 24; $hour++) {
            foreach ([0, 15, 30, 45, 59] as $minute) {
                $value = sprintf('%02d:%02d', $hour, $minute);
                $options[$value] = ['value' => $value, 'selected' => $value === $selected ? 1 : 0];
            }
        }
        return $options;
    }

    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->getInternalXMLTagName() === 'month') {
            return $validators;
        }
        $validators[] = new CallbackValidator(['callback' => function ($value) {
            if ($this->getInternalXMLTagName() === 'hour') {
                // XXX: Missing hours mean all day; unpadded times and equal endpoints remain accepted.
                if ($value === '') {
                    return [];
                }
                $times = explode('-', $value);
                $minutes = [];
                foreach ($times as $time) {
                    $parts = explode(':', $time);
                    if (
                        count($parts) !== 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1]) ||
                        (int)$parts[0] > 23 || (int)$parts[1] > 59
                    ) {
                        return [gettext('Enter a time range such as 8:00-17:00.')];
                    }
                    $minutes[] = (int)$parts[0] * 60 + (int)$parts[1];
                }
                // XXX: The legacy editor rejects overnight ranges.
                return count($minutes) === 2 && $minutes[0] <= $minutes[1] ? [] :
                    [gettext('The start time must be no later than the stop time.')];
            }
            $range = $this->getParentNode();
            if (!$range->position->isEmpty()) {
                return $range->month->isEmpty() && $this->isEmpty() ? [] :
                    [gettext('Select either weekdays or calendar dates.')];
            }
            $months = $range->month->getValues();
            $days = $this->getValues();
            $message = gettext('Provide matching month and day lists containing valid calendar dates.');
            if (!$days || count($months) !== count($days)) {
                return [$message];
            }
            foreach ($months as $index => $month) {
                // XXX: Dates repeat annually, including February 29 in leap years.
                if (
                    !ctype_digit($month) || !ctype_digit($days[$index]) ||
                    !checkdate((int)$month, (int)$days[$index], 2000)
                ) {
                    return [$message];
                }
            }
            return [];
        }]);
        return $validators;
    }

    public function isFieldChanged()
    {
        return parent::isFieldChanged() || ($this->getInternalXMLTagName() === 'day' && (
            $this->getParentNode()->month->isFieldChanged() || $this->getParentNode()->position->isFieldChanged()
        ));
    }

    public function setFieldSeparator($value)
    {
        $this->internalFieldSeparator = $value;
    }

    public function getValues(): array
    {
        // XXX: Keep order and duplicates: month=12,12,1 and day=24,25,1 pair by index, not as independent sets.
        return $this->isSet() ? explode($this->internalFieldSeparator, $this->getValue()) : [];
    }

    public function setValues(array $values)
    {
        $this->setValue(implode($this->internalFieldSeparator, $values));
    }
}
