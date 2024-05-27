<?php

namespace App\Repositories\Brand\Creator\Scripts;

use App\Models\Brand\Creator\BrandCreatorScript;
use App\Promoter;
use App\Repositories\Brand\Creator\Scripts\BCrScriptActionTypes;
use App\Repositories\Brand\Creator\Scripts\BrandCreatorScriptEventTypes;
use App\Repositories\Creator\ClientData\CCWorkingStatuses;
use Illuminate\Http\Request;

/**
 * Комментарий разработчика:
 * Мой очередной любимый собственный паттерн - трактовать создание и изменение как единый процесс.
 *
 * Процесс Setup - это объединение стандартных методов create и edit.
 * Процесс Upsert - объединение стандартных методов store и update (см. класс UpsertBrandCreatorScript).
 *
 * Для определённых моделей я даже использовал единый view-шаблон.
 */

class SetupBrandCreatorScript
{
    public function httpSetup(int $brandId, ?int $bcScriptId, Request $request)
    {
        $brand = Promoter::query()->with('data')->findOrFail($brandId);
        $modelToSetup = null;
        if ($bcScriptId) {
            /** @var BrandCreatorScript $modelToSetup */
            $modelToSetup = $brand->creator_scripts()
                ->with([
                    'email_template_content_record',
                ])
                ->findOrFail($bcScriptId);
            $modelToSetup->append([
                'email_info',
            ]);
        }

        $isUpdating = !!$modelToSetup;
        $isCreating = !$isUpdating;

        if ($request->expectsJson()) {
            return [
                'template' => [
                    'event_type_options' => BrandCreatorScriptEventTypes::i()
                        ->makeAdminSetupOptions(),
                    'status_to_options' => CCWorkingStatuses::i()
                        ->makeAdminSetupOptions(),
                    'action_type_options' => BCrScriptActionTypes::i()
                        ->makeAdminSetupOptions(),
                    'recipient_options' => [
                        'la_admin' => 'LocalAway manager',
                        'creator' => 'Creator',
                    ],
                ],
            ];
        }

        return $isCreating
            ? view('admin.v4-pages.brand.cc.scripts.create', [
                'brand' => $brand,
            ])
            : view('admin.v4-pages.brand.cc.scripts.edit', [
                'brand' => $brand,
                'bcScript' => $modelToSetup,
            ]);
    }
}
