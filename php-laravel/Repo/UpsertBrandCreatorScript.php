<?php

namespace App\Repositories\Brand\Creator\Scripts;

use App\Foundation\Env;
use App\Models\Brand\Creator\BrandCreatorScript;
use App\Models\Brand\Creator\BrandCreatorScriptFire;
use App\Models\Brand\Creator\BrandCreatorScriptTextContent;
use App\Promoter;
use App\Repositories\Brand\Creator\Scripts\BCrScriptActionTypes;
use App\Repositories\Brand\Creator\Scripts\BCrScriptTextContentTypes;
use App\Repositories\Brand\Creator\Scripts\BrandCreatorScriptEventTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * Комментарий разработчика:
 * Мой очередной любимый собственный паттерн - трактовать создание и изменение как единый процесс.
 *
 * По сути, создание и обновление ресурса очень схожи:
 * * валидация
 * * корректировка входных значений
 * * сохранение самой сущности в БД
 * * сохранение relations
 *
 * Процесс Setup - это объединение стандартных методов create и edit (см. класс SetupBrandCreatorScript).
 * Процесс Upsert - объединение стандартных методов store и update.
 */

class UpsertBrandCreatorScript
{
    public function httpUpsert(int $brandId, ?int $bcScriptId, Request $request)
    {
        $brand = Promoter::query()->with('data')->findOrFail($brandId);

        $modelToUpdate = null;
        if ($bcScriptId) {
            $modelToUpdate = $brand->creator_scripts()
                ->findOrFail($bcScriptId);
        }
        $modelToUpdate = $modelToUpdate ?? new BrandCreatorScript([
            'brand_id' => $brandId,
            'params' => [],
        ]);
        $isUpdating = $modelToUpdate->exists;
        $isCreating = !$isUpdating;

        $returnUrl = data_get($request, '_form.back_url')
            ?: route('promoters.creators.scripts.index', $brandId);
        $redirectUrlOnSuccess = $returnUrl;
        $redirectUrlOnError = $isCreating
            ? route('promoters.creators.scripts.create', $brandId)
            : route('promoters.creators.scripts.edit', [
                'promoter' => $brandId,
                'id' => $bcScriptId,
            ])
        ;

        $redirectBackOnFail = function ($errorProvider) use ($redirectUrlOnError) {
            return redirect()
                ->to($redirectUrlOnError)
                ->withErrors($errorProvider)
                ->withInput();
        };

        $allValues = $request->all();

        if ($isUpdating) {
            data_set($allValues, 'params.recipient', $modelToUpdate->params->recipient);
        }
        $emailRecipientInput = data_get($allValues, 'params.recipient');

        $validationRules = $this->makeValidationRules(false, [
            'email_recipient' => $emailRecipientInput,
        ]);
        $validator = $this->makeValidator($allValues, $validationRules);
        if ($validator->fails()) {
            return $redirectBackOnFail($validator);
        }
        $valuesToUpdate = $validator->validated();
        $keysToUpdate = array_keys($valuesToUpdate);
        if (array_key_exists('action_type_id', $valuesToUpdate)) {
            $valuesToUpdate['action_type_id'] = (int) $valuesToUpdate['action_type_id'];
        }
        if (array_key_exists('event_type_id', $valuesToUpdate)) {
            $valuesToUpdate['event_type_id'] = (int) $valuesToUpdate['event_type_id'];
        }
        if (array_key_exists('email', $valuesToUpdate)) {
            $email = $valuesToUpdate['email'];
            if ($isCreating) {
                $email['text'] = $email['text'] ?? '';
                $valuesToUpdate['email'] = $email;
            }
        }

        try {
            $params = Arr::pull($valuesToUpdate, 'params');
            $email = Arr::pull($valuesToUpdate, 'email');

            $modelToUpdate->fill($valuesToUpdate);

            if (in_array('params', $keysToUpdate)) {
                $this->applyParams($modelToUpdate, $params);
            }

            $modelToUpdate->save();

            if (in_array('email', $keysToUpdate)) {
                $this->syncEmailContent($modelToUpdate, $email);
            }

        } catch (\Throwable $e) {
            Env::ddIfLocal($e);
            return $redirectBackOnFail($e->getMessage());
        }

        return redirect()
            ->to($redirectUrlOnSuccess)
            ->with([
                'success_notifications' => [
                    [
                        'text' => $isCreating ? 'New Creator Script Created' : 'Changes saved',
                    ],
                ],
            ])
        ;
    }

    public function syncEmailContent(BrandCreatorScript $bcScript, array $emailDetails)
    {
        /** @var BrandCreatorScriptTextContent $contentRecord */
        $contentRecord = $bcScript->email_template_content_record()->firstOrNew([
            'content_type_id' => BCrScriptTextContentTypes::ID_EMAIL_TEMPLATE,
        ]);
        if (array_key_exists('subject', $emailDetails)) {
            $contentRecord->subject = $emailDetails['subject'];
        }
        if (array_key_exists('text', $emailDetails)) {
            $contentRecord->text = $emailDetails['text'];
        }

        $contentRecord->save();
    }

    public function applyParams(
        BrandCreatorScript $modelToUpdate,
        array $paramsToMerge
    )
    {
        $newParams = $modelToUpdate->params;

        if ($modelToUpdate->action_type_id === BCrScriptActionTypes::ID_SEND_EMAIL) {
            if (array_key_exists('recipient', $paramsToMerge)) {
                $newParams->recipient = $paramsToMerge['recipient'];
            }
        }

        switch ($modelToUpdate->event_type_id) {
            case BrandCreatorScriptEventTypes::ID_WORKING_STATUS_CHANGE:
                if (array_key_exists('status_to', $paramsToMerge)) {
                    $newParams->status_to = (int) $paramsToMerge['status_to'];
                }
                break;
            case BrandCreatorScriptEventTypes::ID_WORKING_STATUS_TIMEOUT:
                if (array_key_exists('status', $paramsToMerge)) {
                    $newParams->status = (int) $paramsToMerge['status'];
                }
                if (array_key_exists('timeout_hours', $paramsToMerge)) {
                    $newParams->timeout_hours = (int) $paramsToMerge['timeout_hours'];
                }
                break;
        }

        $modelToUpdate->params = $newParams;
    }

    public function makeValidator($values, $validationRules)
    {
        return Validator::make($values, $validationRules)
            ->setAttributeNames([
                //
            ]);
    }

    public function makeValidationRules(
        bool $isStoring,
        array $values = []
    ): array
    {
        $maybeRequired = make_maybe_required_cb($isStoring);

        $rules = [
            'name' => 'nullable|string|min:0|max:255',
            'event_type_id' => $maybeRequired('numeric'),
            'action_type_id' => $maybeRequired('numeric'),
            'fire_limit_per_creator' => 'sometimes|nullable|numeric',
            'is_enabled' => $maybeRequired('boolean'),
            #
            'params' => $maybeRequired('array'),
            'params.recipient' => 'sometimes|string',
            'params.status_to' => 'sometimes|numeric',
            #
            'email' => $maybeRequired('array'),
            'email.subject' => 'sometimes|string|max:255',
            'email.text' => 'sometimes|string',
        ];

        $emailRecipient = $values['email_recipient'] ?? null;
        if ($emailRecipient === 'la_admin') {
            $rules['email.subject'] = 'nullable|string|max:255';
            $rules['email.text'] = 'nullable|string|max:255';
        }

        return $rules;
    }

    public function httpDelete(int $brandId, int $bcScriptId, Request $request)
    {
        $brand = Promoter::query()->with('data')->findOrFail($brandId);
        /** @var BrandCreatorScript $modelToDelete */
        $modelToDelete = $brand->creator_scripts()->findOrFail($bcScriptId);

        BrandCreatorScriptTextContent::query()
            ->where([
                'brand_creator_script_id' => $modelToDelete->id,
            ])
            ->delete();
        BrandCreatorScriptFire::query()
            ->where([
                'brand_creator_script_id' => $modelToDelete->id,
            ])
            ->delete();

        $modelToDelete->delete();

        $nextUrl = data_get($request, '_form.back_url')
            ?: route('promoters.creators.scripts.index', $brandId);
        return redirect()
            ->to($nextUrl)
            ->with([
                'success_notifications' => [
                    [
                        'text' => 'Deleted',
                    ],
                ],
            ])
            ;
    }
}
