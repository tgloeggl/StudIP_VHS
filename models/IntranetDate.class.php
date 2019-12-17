<?php


/**
 * @author  <asudau@uos.de>
 *
 * @property int     $id
 * @property string  $user_id
 * @property string  $begin
 * @property string  $end
 * @property string  $notice
 * @property string  $type
 * @property int     $chdate
 * @property int     $mkdate
 */
class IntranetDate extends \SimpleORMap
{

    public $errors = array();

    /**
     * Give primary key of record as param to fetch
     * corresponding record from db if available, if not preset primary key
     * with given value. Give null to create new record
     *
     * @param mixed $id primary key of table
     */
    protected static function configure($config = array())
    {
        $config['db_table'] = 'intranet_dates';
        parent::configure($config);
    }

}

