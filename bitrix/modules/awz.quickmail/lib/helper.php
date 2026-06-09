<?php
namespace Awz\Quickmail;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\Mail\Internal\EventTable;


class Helper {

    const MODULE_ID = 'awz.quickmail';
    const MASK_ACTIVE = 2;
    const MASK_DELETE = 4;

    public static function isActive(int $id): bool{
        return self::getMask($id) & self::MASK_ACTIVE;
    }

    public static function isDelete(int $id): bool
    {
        return self::getMask($id) & self::MASK_DELETE;
    }

    public static function getMask(int $id){

        static $opts;

        if(!$opts){
            try{
                $opts = unserialize(Option::get(static::MODULE_ID, "OPTS", "",""), ['allowed_classes'=>false]);
            }catch (\Exception $e){
                $opts = [];
            }
        }

        return $opts[$id] ?? 0;
    }

    /**
     * Отправляет событие немедленно через sendImmediate
     * 
     * @param int $eventId ID события из таблицы b_event
     * @return bool
     */
    public static function sendImmediate(int $eventId): bool
    {
        try {
            // Получаем событие из базы
            $event = EventTable::getById($eventId)->fetch();
            
            if (!$event) {
                return false;
            }

            // Создаем объект события для отправки
            $eventObj = Event::createInstance($event);
            
            // Отправляем немедленно
            $eventObj->sendImmediate();
            
            return true;
        } catch (\Exception $e) {
            // Логируем ошибку
            \CEventLog::Add(array(
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'AWZ_QUICKMAIL_ERROR',
                'MODULE_ID' => self::MODULE_ID,
                'DESCRIPTION' => 'Ошибка при немедленной отправке события ID ' . $eventId . ': ' . $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Удаляет событие из таблицы b_event
     * 
     * @param int $eventId ID события из таблицы b_event
     * @return bool
     */
    public static function deleteEvent(int $eventId): bool
    {
        try {
            EventTable::delete($eventId);
            return true;
        } catch (\Exception $e) {
            // Логируем ошибку
            \CEventLog::Add(array(
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'AWZ_QUICKMAIL_ERROR',
                'MODULE_ID' => self::MODULE_ID,
                'DESCRIPTION' => 'Ошибка при удалении события ID ' . $eventId . ': ' . $e->getMessage()
            ));
            return false;
        }
    }
}