<?php
namespace Awz\Quickmail;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Mail\Internal\EventMessageTable;
use Bitrix\Main\Mail\Internal\EventTable;
use Awz\Quickmail\Access\AccessController;
use Awz\Quickmail\Logger as QuickmailLogger;

class Handlers {

    public static $lockHandler = [];

    public static function onPageStart()
    {

    }

    public static function OnBeforeProlog()
    {
        $moduleId = Helper::MODULE_ID;

        if(!\check_bitrix_sessid()){
            return;
        }

        $request = Application::getInstance()->getContext()->getRequest();
        if ($request->getRequestMethod() !== 'POST') {
            return;
        }

        // Проверяем, что мы на странице редактирования почтового шаблона
        $curPage = $request->getRequestUri();
        if (strpos($curPage, '/bitrix/admin/message_edit.php') === false) {
            return;
        }

        if($request->get('AWZ_QUICKMAIL_HIDDEN') != 'Y') {
            return;
        }

        if (!Loader::includeModule($moduleId)) {
            return;
        }


        // Проверяем права на редактирование настроек
        if (!AccessController::isEditSettings()) {
            QuickmailLogger::warning('OnBeforeProlog: Нет прав на редактирование настроек', [
                'method' => __METHOD__
            ]);
            return;
        }

        $templateId = (int) $request->get('ID');
        if (!$templateId) {
            QuickmailLogger::warning('OnBeforeProlog: Не указан ID шаблона', [
                'method' => __METHOD__
            ]);
            return;
        }

        $finOptions = [];
        try {
            $opts = unserialize(Option::get($moduleId, "OPTS", "", ""), ['allowed_classes' => false]);
            if (is_array($opts)) {
                $finOptions = $opts;
            }
        } catch (\Exception $e) {
            QuickmailLogger::error('OnBeforeProlog: Ошибка при получении настроек', [
                'method' => __METHOD__,
                'error' => $e->getMessage()
            ]);
            $finOptions = [];
        }

        $active = $request->get('AWZ_QUICKMAIL_ACTIVE') === 'Y';
        $delete = $request->get('AWZ_QUICKMAIL_DELETE') === 'Y';

        $mask = 0;
        if ($active) {
            $mask = $mask | Helper::MASK_ACTIVE;
        }
        if ($delete) {
            $mask = $mask | Helper::MASK_DELETE;
        }

        if ($mask > 0) {
            $finOptions[$templateId] = $mask;
        } else {
            unset($finOptions[$templateId]);
        }

        Option::set($moduleId, "OPTS", serialize($finOptions), "");
        
        QuickmailLogger::info('OnBeforeProlog: Настройки сохранены', [
            'method' => __METHOD__,
            'template_id' => $templateId,
            'mask' => $mask,
            'active' => $active,
            'delete' => $delete
        ]);
    }

    public static function OnAdminTabControlBegin(&$form)
    {
        $moduleId = Helper::MODULE_ID;
        $request = Application::getInstance()->getContext()->getRequest();

        $curPage = $request->getRequestUri();
        if (strpos($curPage, '/bitrix/admin/message_edit.php') === false) {
            return;
        }

        if (!Loader::includeModule($moduleId)) {
            QuickmailLogger::warning('OnAdminTabControlBegin: Модуль не загружен', [
                'method' => __METHOD__,
                'module_id' => $moduleId
            ]);
            return;
        }

        // Проверяем права на просмотр настроек
        if (!AccessController::isViewSettings()) {
            QuickmailLogger::debug('OnAdminTabControlBegin: Нет прав на просмотр настроек', [
                'method' => __METHOD__
            ]);
            return;
        }

        // Получаем ID шаблона из URL или запроса

        $templateId = (int) $request->get('ID');
        if (!$templateId) {
            QuickmailLogger::warning('OnAdminTabControlBegin: Не указан ID шаблона', [
                'method' => __METHOD__
            ]);
            return;
        }

        $isActive = Helper::isActive($templateId);
        $isDelete = Helper::isDelete($templateId);
        $canEdit = AccessController::isEditSettings();

        Loc::loadMessages(__FILE__);

        QuickmailLogger::debug('OnAdminTabControlBegin: Рендер настроек для шаблона', [
            'method' => __METHOD__,
            'template_id' => $templateId,
            'is_active' => $isActive,
            'is_delete' => $isDelete,
            'can_edit' => $canEdit
        ]);

        // Формируем HTML для вставки после tr с LANGUAGE_ID
        $html = '
<tr class="heading">
    <td colspan="2">' . Loc::getMessage('AWZ_QUICKMAIL_ADMIN_TITLE') . '
    <input type="hidden" name="AWZ_QUICKMAIL_HIDDEN" value="Y">
    </td>
</tr>
<tr>
    <td style="width:40%">' . Loc::getMessage('AWZ_QUICKMAIL_ADMIN_ACTIVE') . '</td>
    <td style="width:60%">
        <input type="checkbox" value="Y" name="AWZ_QUICKMAIL_ACTIVE" ' . ($isActive ? 'checked' : '') . ' ' . (!$canEdit ? 'disabled' : '') . '>
    </td>
</tr>
<tr>
    <td style="width:40%">' . Loc::getMessage('AWZ_QUICKMAIL_ADMIN_DELETE') . '</td>
    <td style="width:60%">
        <input type="checkbox" value="Y" name="AWZ_QUICKMAIL_DELETE" ' . ($isDelete ? 'checked' : '') . ' ' . (!$canEdit ? 'disabled' : '') . '>
    </td>
</tr>';
        if(Option::get($moduleId, 'DISABLED', 'N', '') == 'Y'){
            $html .= '<tr>
    <td colspan="2" style="text-align: center;">
        '.Loc::getMessage('AWZ_QUICKMAIL_ADMIN_DISABLED').'
    </td>
</tr>';
        }

        // Добавляем вкладку с настройками модуля на страницу почтового шаблона
        $form->tabs[] = array(
            "DIV" => "awz_quickmail_settings",
            "TAB" => Loc::getMessage('AWZ_QUICKMAIL_ADMIN_TAB'),
            "ICON" => "",
            "TITLE" => Loc::getMessage('AWZ_QUICKMAIL_ADMIN_TITLE'),
            "CONTENT" => '' . $html . ''
        );


    }

    /**
     * Обработчик события OnBeforeEventSend
     * Отменяет отправку если шаблон отмечен для удаления
     */
    public static function OnBeforeEventSend(&$arFields, &$eventMessage)
    {
        // Проверяем, отключен ли модуль
        $disabled = Option::get(Helper::MODULE_ID, 'DISABLED', 'N', '');
        if ($disabled === 'Y') {
            QuickmailLogger::debug('OnBeforeEventSend: Модуль отключен', [
                'method' => __METHOD__
            ]);
            return;
        }

        /*QuickmailLogger::info('OnBeforeEventSend: Запуск события', [
            'method' => __METHOD__,
            'arFields' => $arFields,
            'eventMessage' => $eventMessage
        ]);*/

        $messageId = (int)$eventMessage['ID'];
        $sended = Handlers::$lockHandler[$messageId] ?? 0;
        if($sended) return;

        if (Helper::isActive($messageId))
        {
            QuickmailLogger::info('OnBeforeEventSend: Отмена отправки - шаблон активен для немедленной отправки', [
                'method' => __METHOD__,
                'message_id' => $messageId,
                'event_name' => $eventMessage['EVENT_NAME'] ?? 'unknown'
            ]);
            return false;
        }
    }

    /**
     * Обработчик события OnAfterAdd
     * Отправляет письмо немедленно через sendImmediate если шаблон отмечен
     * и удаляет из b_event если указана опция удаления
     */
    public static function OnAfterEventAdd(\Bitrix\Main\Event $event)
    {
        // Проверяем, отключен ли модуль
        $disabled = Option::get(Helper::MODULE_ID, 'DISABLED', 'N', '');
        if ($disabled === 'Y') {
            QuickmailLogger::debug('OnAfterEventAdd: Модуль отключен', [
                'method' => __METHOD__
            ]);
            return;
        }

        // Получаем ID записи в таблице b_event
        $eventId = $event->getParameter('primary');
        if(is_array($eventId)) $eventId = $eventId[array_keys($eventId)[0]];

        if (!$eventId) {
            QuickmailLogger::warning('OnAfterEventAdd: Не получен ID события', [
                'method' => __METHOD__
            ]);
            return;
        }

        // Получаем данные события из b_event
        $eventData = \Bitrix\Main\Mail\Internal\EventTable::getById($eventId)->fetch();
        
        if (!$eventData) {
            QuickmailLogger::error('OnAfterEventAdd: Не найдены данные события', [
                'method' => __METHOD__,
                'event_id' => $eventId
            ]);
            return;
        }

        $messageId = (int)($eventData['MESSAGE_ID'] ?? 0);
        $eventName = $eventData['EVENT_NAME'] ?? '';

        QuickmailLogger::debug('OnAfterEventAdd: Обработка нового события', [
            'method' => __METHOD__,
            'event_id' => $eventId,
            'message_id' => $messageId,
            'event_name' => $eventName
        ]);

        // Если MESSAGE_ID существует, проверяем конкретный шаблон
        if ($messageId > 0) {
            // Если шаблон отмечен для немедленной отправки
            if (Helper::isActive($messageId))
            {
                QuickmailLogger::info('OnAfterEventAdd: Шаблон активен для немедленной отправки', [
                    'method' => __METHOD__,
                    'event_id' => $eventId,
                    'message_id' => $messageId
                ]);
                
                // Отправляем немедленно
                $sendResult = Helper::sendImmediate($eventId);
                
                if ($sendResult) {
                    QuickmailLogger::info('OnAfterEventAdd: Немедленная отправка успешна', [
                        'method' => __METHOD__,
                        'event_id' => $eventId,
                        'message_id' => $messageId
                    ]);
                    
                    // Если отправка успешна и указана опция удаления
                    if (Helper::isDelete($messageId)) {
                        QuickmailLogger::info('OnAfterEventAdd: Удаление события после отправки', [
                            'method' => __METHOD__,
                            'event_id' => $eventId,
                            'message_id' => $messageId
                        ]);
                        Helper::deleteEvent($eventId);
                    }
                } else {
                    QuickmailLogger::error('OnAfterEventAdd: Ошибка немедленной отправки', [
                        'method' => __METHOD__,
                        'event_id' => $eventId,
                        'message_id' => $messageId
                    ]);
                }
            }
        } elseif ($eventName) {
            
            // Если MESSAGE_ID отсутствует, удаляем текущее событие и создаём копии для каждого шаблона
            // Получаем все активные шаблоны для этого события
            $templates = \Bitrix\Main\Mail\Internal\EventMessageTable::getList([
                'filter' => [
                    '=EVENT_NAME' => $eventName,
                    '=ACTIVE' => 'Y'
                ],
                'select' => ['ID']
            ])->fetchCollection();

            $isOpts = false;
            foreach($templates as $tmplOb){
                if(Helper::isActive($tmplOb->getId()) || Helper::isDelete($tmplOb->getId())){
                    $isOpts = true;
                    break;
                }
            }


            if (!empty($templates) && $isOpts) {
                QuickmailLogger::info('OnAfterEventAdd: Найдены отмеченные опциями шаблоны', [
                    'method' => __METHOD__,
                    'event_id' => $eventId,
                    'event_name' => $eventName,
                    'templates_count' => count($templates)
                ]);
                
                // Удаляем текущее событие без MESSAGE_ID
                Helper::deleteEvent($eventId);

                // Создаём копии для каждого шаблона с указанием MESSAGE_ID
                foreach ($templates as $template) {
                    $templateId = $template->getId();
                    QuickmailLogger::debug('OnAfterEventAdd: Создание копии для шаблона '.$eventName, [
                        'method' => __METHOD__,
                        'event_id' => $eventId,
                        'template_id' => $templateId
                    ]);
                    
                    // Создаём новую запись с указанием MESSAGE_ID
                    $newEvent = \Bitrix\Main\Mail\Internal\EventTable::add([
                        'EVENT_NAME' => $eventName,
                        'MESSAGE_ID' => $templateId,
                        'C_FIELDS' => $eventData['C_FIELDS'] ?? null,
                        'LID' => $eventData['LID'] ?? null,
                        'DATE_INSERT' => new \Bitrix\Main\Type\DateTime(),
                        'SUCCESS_EXEC' => $eventData['SUCCESS_EXEC'] ?? null,
                        'DUPLICATE' => $eventData['DUPLICATE'] ?? null,
                        'LANGUAGE_ID' => $eventData['LANGUAGE_ID'] ?? null,
                    ]);
                    if(!$newEvent->isSuccess()){
                        QuickmailLogger::error('OnAfterEventAdd: Ошибка при создании копии события', [
                            'method' => __METHOD__,
                            'event_id' => $eventId,
                            'template_id' => $templateId,
                            'errors' => $newEvent->getErrorMessages()
                        ]);
                    } else {
                        QuickmailLogger::info('OnAfterEventAdd: Копия события создана', [
                            'method' => __METHOD__,
                            'event_id' => $eventId,
                            'template_id' => $templateId,
                            'new_event_id' => $newEvent->getId()
                        ]);

                        // Отправляем немедленно
                        /*$sendResult = Helper::sendImmediate($newEvent->getId());

                        if ($sendResult) {
                            QuickmailLogger::info('OnAfterEventAdd: Немедленная отправка успешна', [
                                'method' => __METHOD__,
                                'event_id' => $newEvent->getId(),
                                'message_id' => $templateId
                            ]);

                            // Если отправка успешна и указана опция удаления
                            if (Helper::isDelete($templateId)) {
                                QuickmailLogger::info('OnAfterEventAdd: Удаление события после отправки', [
                                    'method' => __METHOD__,
                                    'event_id' => $newEvent->getId(),
                                    'message_id' => $templateId
                                ]);
                                Helper::deleteEvent($eventId);
                            }
                        } else {
                            QuickmailLogger::error('OnAfterEventAdd: Ошибка немедленной отправки', [
                                'method' => __METHOD__,
                                'event_id' => $newEvent->getId(),
                                'message_id' => $templateId
                            ]);
                        }*/
                    }
                }
            } else {
                QuickmailLogger::warning('OnAfterEventAdd: Нет отмеченных опциями шаблонов для события', [
                    'method' => __METHOD__,
                    'event_id' => $eventId,
                    'event_name' => $eventName
                ]);
            }
        }

    }

}