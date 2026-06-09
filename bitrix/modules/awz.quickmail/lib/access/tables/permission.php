<?php
namespace Awz\Quickmail\Access\Tables;

use Bitrix\Main\Access\Permission\AccessPermissionTable;

class PermissionTable extends AccessPermissionTable
{
    public static function getTableName()
    {
        return 'awz_quickmail_permission';
    }
}