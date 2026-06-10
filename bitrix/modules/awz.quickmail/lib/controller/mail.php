<?php

namespace Awz\Quickmail\Controller;

use Awz\Quickmail\Filters\HookAuth;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Bitrix\Main\Config\Option;

class Mail extends Controller
{
    /**
     * Конфигурация действий контроллера
     *
     * @return array
     */
    public function configureActions()
    {
        $config = [
            'webhook' => [
                'prefilters' => [
                    new HookAuth(['keyOption' => 'HOOK_KEY'])
                ]
            ],
        ];

        return $config;
    }

    /**
     * Обработка вебхука
     *
     * @param string $key Ключ доступа (проверяется в префильтре HookAuth)
     * @return array
     */
    public function webhookAction($key = '')
    {
        return [];
    }

    /**
     * Финализация ответа - вывод в формате JSON для Telegram
     *
     * @param \Bitrix\Main\Response $response
     */
    public function finalizeResponse(\Bitrix\Main\Response $response)
    {
        $content = $response->getContent();
        $response->setContent($content);
    }
}