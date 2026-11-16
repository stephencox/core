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

namespace OPNsense\Firewall\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class ScheduleController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'schedule';
    protected static $internalModelClass = 'OPNsense\Firewall\Schedule';

    public function searchItemAction()
    {
        $result = $this->searchBase('schedule', ['name', 'descr'], 'name');
        foreach ($result['rows'] as &$row) {
            $row['uuid'] = $row['name'];
        }
        return $result;
    }

    // Names and range positions also work for legacy entries without persisted UUIDs.
    private function findSchedule($name)
    {
        foreach ($this->getModel()->schedule->iterateItems() as $schedule) {
            if ($schedule->name->isEqual($name)) {
                return $schedule;
            }
        }
        return null;
    }

    private function findRange($id)
    {
        [$name, $index] = array_pad(explode('.', $id, 2), 2, '');
        $schedule = $this->findSchedule($name);
        if ($schedule === null || !ctype_digit($index)) {
            return null;
        }
        return array_values($schedule->timerange->getChildren())[(int)$index] ?? null;
    }

    public function getItemAction($name = null)
    {
        $schedule = $name === null ? $this->getModel()->schedule->add() : $this->findSchedule($name);
        if ($schedule === null) {
            return [];
        }
        return ['schedule' => $schedule->getNodes()];
    }

    public function addItemAction()
    {
        return $this->addBase('schedule', 'schedule');
    }

    public function setItemAction($name)
    {
        Config::getInstance()->lock();
        $schedule = $this->findSchedule($name);
        if ($schedule === null) {
            return ['result' => 'failed'];
        }
        return $this->setBase('schedule', 'schedule', $schedule->getAttribute('uuid'));
    }

    public function delItemAction($names)
    {
        Config::getInstance()->lock();
        $uuids = [];
        foreach (explode(',', $names) as $name) {
            $schedule = $this->findSchedule($name);
            if ($schedule === null) {
                return ['result' => 'failed'];
            }
            $uuids[] = $schedule->getAttribute('uuid');
        }
        return $this->delBase('schedule', implode(',', $uuids));
    }

    public function searchRangeAction()
    {
        $schedule = $this->findSchedule($this->request->get('schedule', 'string', ''));
        $rows = [];
        if ($schedule !== null) {
            foreach (array_values($schedule->timerange->getChildren()) as $index => $range) {
                $rows[] = array_merge(
                    $range->getNodeContent(),
                    ['uuid' => $schedule->name->getValue() . '.' . $index]
                );
            }
        }
        return $this->searchRecordsetBase($rows);
    }

    public function getRangeAction($id = null)
    {
        if ($id === null) {
            $schedule = $this->findSchedule($this->request->get('schedule', 'string', ''));
            if ($schedule === null) {
                return [];
            }
            $range = $schedule->timerange->add();
        } else {
            $range = $this->findRange($id);
            if ($range === null) {
                return [];
            }
            $schedule = $range->getParentNode()->getParentNode();
        }
        return [
            'timerange' => $range->getNodes(),
            'editor' => [
                'start' => $range->hour->getTimeOptions(0),
                'stop' => $range->hour->getTimeOptions(1)
            ],
            'schedule' => $schedule->name->getValue()
        ];
    }

    public function addRangeAction()
    {
        Config::getInstance()->lock();
        $schedule = $this->findSchedule($this->request->get('schedule', 'string', ''));
        if ($schedule === null) {
            return ['result' => 'failed'];
        }
        return $this->addBase('timerange', $schedule->timerange->__reference);
    }

    public function setRangeAction($id)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        Config::getInstance()->lock();
        $range = $this->findRange($id);
        if ($range === null) {
            return ['result' => 'failed'];
        }
        return $this->setBase('timerange', $range->getParentNode()->__reference, $range->getAttribute('uuid'));
    }

    public function delRangeAction($ids)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        Config::getInstance()->lock();
        // Resolve all positions before deleting, so bulk deletion cannot shift the remaining indexes.
        $ranges = [];
        foreach (explode(',', $ids) as $id) {
            $range = $this->findRange($id);
            if ($range === null) {
                return ['result' => 'failed'];
            }
            $ranges[] = $range;
        }
        foreach ($ranges as $range) {
            $range->getParentNode()->del($range->getAttribute('uuid'));
        }
        return $this->save();
    }

    protected function save($validateFullModel = false, $disable_validation = false)
    {
        // Always validate deletions too: protect referenced schedules.
        return parent::save($validateFullModel, false);
    }

    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            (new Backend())->configdRun('filter reload skip_alias');
            return ['status' => 'ok'];
        }
        return ['status' => 'failed'];
    }
}
