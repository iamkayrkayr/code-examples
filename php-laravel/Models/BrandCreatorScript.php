<?php

namespace App\Models\Brand\Creator;

use App\Foundation\Eloquent\EloquentUtils;
use App\Models\Concerns\ExtendedAppModelTrait;
use App\Promoter;
use App\Repositories\Brand\Creator\Scripts\BCrScriptAutoNamer;
use App\Repositories\Brand\Creator\Scripts\BCrScriptParams;
use App\Repositories\Brand\Creator\Scripts\BCrScriptTextContentTypes;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Fluent;

/**
 * Комментарий разработчика:
 * Из-за специфики данного проекта часто приходилось добавлять к Моделям такие данные, про которые было непонятно -
 * нужны ли оны будут в дальнейшем? стоит ли выделять отдельную колонку под них?
 * Было решено использовать json-колонку общего назначения.
 *
 * С помощью кастомных getter и mutator json-колонка `params` кастуется в виртуальную Модель BCrScriptParams.
 * Это лучше, чем использовать стандарнтый каст 'array' - поскольку в моём случае есть возможность type-hinting,
 * коррекция содержимого колонки "на лету" и возможность объявления дополнительных методов над объектом.
 *
 * Также см. класс BCrScriptParams.
 */

/**
 * * db:
 * @property-read int $id
 * @property int $brand_id
 * @property string $name
 * @property int $event_type_id
 * @property int $action_type_id
 * @property int|null $fire_limit_per_creator
 * @property bool $is_enabled
 * @property BCrScriptParams $params
 * @property-read int $created_at
 * @property-read int $updated_at
 *
 * * relations:
 * @property-read EloquentCollection|\App\Models\Brand\Creator\BrandCreatorScriptFire $fire_records
 * @property-read \App\Models\Brand\Creator\BrandCreatorScriptTextContent|null $email_template_content_record
 * @property Promoter $brand
 *
 * * computed:
 * @property-read Fluent $email_info
 */
class BrandCreatorScript extends Model
{
    use ExtendedAppModelTrait;

    protected $fillable = [
        'brand_id',
        'name',
        'event_type_id',
        'action_type_id',
        'fire_limit_per_creator',
        'is_enabled',
        'params',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function(BrandCreatorScript $bcScript) {
            $bcScript->loadMissing([
                'brand.data',
            ]);
            $bcScript->name = $bcScript->name ?: (new BCrScriptAutoNamer($bcScript))->autoPickName();
        });
    }

    public function getParamsAttribute(): BCrScriptParams
    {
        return new BCrScriptParams(json_decode($this->attributes['params']));
    }

    public function setParamsAttribute($params)
    {
        $this->attributes['params'] = EloquentUtils::toJson($params);
    }

    public function getEmailInfoAttribute(): Fluent
    {
        $emailTemplateContentRecord = $this->email_template_content_record;
        return new Fluent([
            'subject' => $emailTemplateContentRecord ? $emailTemplateContentRecord->subject : '',
            'text' => $emailTemplateContentRecord ? $emailTemplateContentRecord->text : '',
        ]);
    }

    public function fire_records(): HasMany
    {
        return $this->hasMany(\App\Models\Brand\Creator\BrandCreatorScriptFire::class, 'brand_creator_script_id', 'id');
    }

    public function email_template_content_record(): HasOne
    {
        return $this->hasOne(
            \App\Models\Brand\Creator\BrandCreatorScriptTextContent::class,
            'brand_creator_script_id',
            'id',
        )
            ->where('content_type_id', BCrScriptTextContentTypes::ID_EMAIL_TEMPLATE);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Promoter::class, 'brand_id', 'id');
    }
}
