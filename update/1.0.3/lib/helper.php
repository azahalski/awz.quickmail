<?php
namespace Awz\Quickmail;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\Mail\Internal\EventTable;
use Awz\Quickmail\Logger as QuickmailLogger;


class Helper {

    const MODULE_ID = 'awz.quickmail';
    const MASK_ACTIVE = 2;
    const MASK_DELETE = 4;

    public static function isActive(int $id): bool{
        $isActive = self::getMask($id) & self::MASK_ACTIVE;
        return $isActive;
    }

    public static function isDelete(int $id): bool
    {
        $isDelete = self::getMask($id) & self::MASK_DELETE;
        return $isDelete;
    }

    public static function getMask(int $id){

        static $opts;

        if(!$opts){
            try{
                $opts = unserialize(Option::get(static::MODULE_ID, "OPTS", "",""), ['allowed_classes'=>false]);
            }catch (\Exception $e){
                QuickmailLogger::error('Ошибка при получении настроек масок', [
                    'method' => __METHOD__,
                    'error' => $e->getMessage()
                ]);
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
        QuickmailLogger::info('Начало немедленной отправки события', [
            'method' => __METHOD__,
            'event_id' => $eventId
        ]);
        
        try {
            // Получаем событие из базы
            $event = EventTable::getById($eventId)->fetch();
            $messageId = (int)$event['MESSAGE_ID'];
            if(!$messageId) {
                QuickmailLogger::warning('Событие не отправлено, нет MESSAGE_ID', [
                    'method' => __METHOD__,
                    'event_id' => $eventId,
                    'messageId' => $messageId
                ]);
                return false;
            }
            
            if (!$event) {
                QuickmailLogger::error('Событие не найдено в базе', [
                    'method' => __METHOD__,
                    'event_id' => $eventId
                ]);
                return false;
            }
            
            QuickmailLogger::debug('Событие найдено, отправляем', [
                'method' => __METHOD__,
                'event_id' => $eventId,
                'event_name' => $event['EVENT_NAME'] ?? 'unknown'
            ]);
            
            Handlers::$lockHandler[$messageId] = 1;
            $flag = Event::sendImmediate($event);
            Handlers::$lockHandler[$messageId] = 0;
            
            if($flag == 'Y') {
                QuickmailLogger::info('Событие успешно отправлено немедленно', [
                    'method' => __METHOD__,
                    'event_id' => $eventId
                ]);
                return true;
            } else {
                QuickmailLogger::warning('Событие не отправлено', [
                    'method' => __METHOD__,
                    'event_id' => $eventId,
                    'flag' => $flag
                ]);
            }
        } catch (\Exception $e) {
            QuickmailLogger::error('Ошибка при немедленной отправке события', [
                'method' => __METHOD__,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

        }
        return false;
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
            QuickmailLogger::info('Удаление события', [
                'method' => __METHOD__,
                'event_id' => $eventId
            ]);
            return true;
        } catch (\Exception $e) {
            QuickmailLogger::error('Ошибка при удалении события', [
                'method' => __METHOD__,
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}