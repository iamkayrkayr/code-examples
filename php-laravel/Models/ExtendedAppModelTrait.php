<?php

namespace App\Models\Concerns;

use App\Foundation\Env;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Комментарий разработчика:
 * Проект, из которого взят этот файл, сделан на Laravel 7 - в котором нет встроенного механизма отслеживания
 * lazy-loading для relations.
 * Для этого trait-а я взял подходящий отрывок из исходников Laravel 8.
 *
 * Также тут переопределён метод Model::asDateTime. По умолчанию, если в Модель записывается Carbon объект с
 * отличной от серверной таймзоной, то при последующем получении момент "искажается" - поскольку в БД пишется
 * строковое представление datetime (не timestamp). С использованием текущего trait-а это поведение можно опционально
 * исправить.
 */

/**
 * Features:
 * * can track lazy loading (it is supported out-of-the-box only since Laravel 8)
 * * writes correct datetime values into DB when writing datetimes with another timezone
 *
 * @mixin Model
 */
trait ExtendedAppModelTrait
{
    protected bool $doApplyTimezoneFix = false;

    protected function getRelationshipFromMethod($method)
    {
        if (Env::doPreventEloquentLazyLoading()) {
            $this->handleLazyLoadingViolation($method);
        }

        $relation = $this->$method();

        if (! $relation instanceof Relation) {
            if (is_null($relation)) {
                throw new \LogicException(sprintf(
                    '%s::%s must return a relationship instance, but "null" was returned. Was the "return" keyword used?', static::class, $method
                ));
            }

            throw new \LogicException(sprintf(
                '%s::%s must return a relationship instance.', static::class, $method
            ));
        }

        return tap($relation->getResults(), function ($results) use ($method) {
            $this->setRelation($method, $results);
        });
    }

    protected function handleLazyLoadingViolation($relation)
    {
        $class = get_class($this);
        throw new \RuntimeException(
            "Attempted to lazy load [{$relation}] on model [{$class}] but lazy loading is disabled.",
        );
    }

    protected function asDateTime($value)
    {
        if ($this->doApplyTimezoneFix && ($value instanceof \DateTimeInterface)) {
            $value->setTimezone(Carbon::now()->getTimezone());
        }

        return parent::asDateTime($value);
    }
}
