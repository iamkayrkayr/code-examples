<?php

namespace App\Http\Controllers\Admin\Brand\Creator;

use App\Promoter;
use App\Repositories\Brand\Creator\Scripts\SetupBrandCreatorScript;
use App\Repositories\Brand\Creator\Scripts\UpsertBrandCreatorScript;
use Illuminate\Http\Request;

/**
 * Комментарий разработчика:
 * Мой очередной любимый собственный паттерн - трактовать создание и изменение как единый процесс.
 * ПОдробнее: см. классы UpsertBrandCreatorScript и SetupBrandCreatorScript.
 *
 * С течением времени логика модели разрасталась, поэтому было решено раскидать методы ресурсов по отдельным классам.
 * В таком случае возможно переиспользовать логику создания/сохранения связанной модели в других местах
 * (скажем, в других контроллерах).
 *
 * Также использован patch вместо update - поскольку логика PUT (полностью заменить абсолютно все поля) может быть
 * заменена логикой PATCH (частичное обновление, включающая возможность и полного обновления).
 */

class BrandCreatorScriptsController
{
    public function index(int $brandId, Request $request)
    {
        $brand = Promoter::query()->with('data')->findOrFail($brandId);

        $brandCreatorScripts = $brand->creator_scripts()
            ->with([
                'email_template_content_record',
            ])
            ->orderBy('event_type_id')
            ->orderBy('action_type_id')
            ->orderByDesc('is_enabled')
            ->get()
        ;

        return view('admin.v4-pages.brand.cc.scripts.index', [
            'brand' => $brand,
            'items' => $brandCreatorScripts,
        ]);
    }

    public function create(int $brandId, Request $request)
    {
        return (new SetupBrandCreatorScript())->httpSetup($brandId, null, $request);
    }

    public function store(int $brandId, Request $request)
    {
        return (new UpsertBrandCreatorScript())->httpUpsert($brandId, null, $request);
    }

    public function edit(int $brandId, int $bcScriptId, Request $request)
    {
        return (new SetupBrandCreatorScript())->httpSetup($brandId, $bcScriptId, $request);
    }

    public function patch(int $brandId, int $bcScriptId, Request $request)
    {
        return (new UpsertBrandCreatorScript())->httpUpsert($brandId, $bcScriptId, $request);
    }

    public function destroy(int $brandId, int $bcScriptId, Request $request)
    {
        return (new UpsertBrandCreatorScript())->httpDelete($brandId, $bcScriptId, $request);
    }
}
