<?php

namespace App\Repositories\Brand\Creator\Scripts;

use App\Repositories\Creator\ClientData\CCWorkingStatus;
use App\Repositories\Creator\ClientData\CCWorkingStatuses;
use Illuminate\Support\Fluent;

/**
 * Комментарий разработчика:
 * Мой любимый собственный паттерн - виртуальные Модели.
 */

/**
 * @property int|null $status_from
 * @property int|null $status_to
 * For status change events: previous and target status ids
 *
 * @property string|null $recipient
 * 'creator'
 * 'la_admin'
 *
 * @property int|null $timeout_hours
 * For timeout events.
 *
 * @property int|null $status
 * For status timeout events.
 *
 */
class BCrScriptParams extends Fluent
{
    public function getStatusTo(): CCWorkingStatus
    {
        return CCWorkingStatuses::i()->findOrFail($this->status_to);
    }

    public function getStatus(): CCWorkingStatus
    {
        return CCWorkingStatuses::i()->findOrFail($this->status);
    }

    public function getTimeoutInRoundDays(): int
    {
        return round((int)($this->timeout_hours) / 24);
    }
}
