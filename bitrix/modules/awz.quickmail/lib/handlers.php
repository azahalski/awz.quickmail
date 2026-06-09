<?php
namespace Awz\Quickmail;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Mail\Internal\EventMessageTable;
use Awz\Quickmail\Access\AccessController;

class Handlers {

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
            return;
        }

        $templateId = (int) $request->get('ID');
        if (!$templateId) {
            return;
        }

        $finOptions = [];
        try {
            $opts = unserialize(Option::get($moduleId, "OPTS", "", ""), ['allowed_classes' => false]);
            if (is_array($opts)) {
                $finOptions = $opts;
            }
        } catch (\Exception $e) {
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
            return;
        }

        // Проверяем права на просмотр настроек
        if (!AccessController::isViewSettings()) {
            return;
        }

        // Получаем ID шаблона из URL или запроса

        $templateId = (int) $request->get('ID');
        if (!$templateId) {
            return;
        }

        $isActive = Helper::isActive($templateId);
        $isDelete = Helper::isDelete($templateId);
        $canEdit = AccessController::isEditSettings();

        Loc::loadMessages(__FILE__);

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
        // Если шаблон отмечен для удаления, отменяем отправку
        if (Helper::isDelete((int)$eventMessage['ID']))
        {
            return false;
        }
    }

    /**
     * Обработчик события OnAfterEventAdd
     * Отправляет письмо немедленно через sendImmediate если шаблон отмечен
     * и удаляет из b_event если указана опция удаления
     */
    public static function OnAfterEventAdd($eventId, $arFields, $eventMessage)
    {
        // Проверяем, отключен ли модуль
        $disabled = Option::get(Helper::MODULE_ID, 'DISABLED', 'N', '');
        if ($disabled === 'Y') {
            return;
        }

        $templateId = (int)$eventMessage['ID'];
        
        // Если шаблон отмечен для немедленной отправки
        if (Helper::isActive($templateId))
        {
            // Отправляем немедленно
            $sendResult = Helper::sendImmediate($eventId);
            
            if ($sendResult) {
                // Если отправка успешна и указана опция удаления
                if (Helper::isDelete($templateId)) {
                    Helper::deleteEvent($eventId);
                }
            }
        }
    }

}