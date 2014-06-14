<?php
/**
 * 从数据库连接
 *
 * Read More: http://devtoby.github.io/yii-db-read-write-splitting
 *
 * File: MDbSlaveConnection.php
 * Date: 14-6-14
 * Author: Toby<quflylong@qq.com>
 */

class MDbSlaveConnection extends CDbConnection
{
    /**
     * @var int 连接数据库超时时间
     */
    public $timeout = 3;

    /**
     * @var int 是否需要读操作检查，开启后会检查sql是否为查询语句
     */
    public $isNeedReadCheck = true;

    /**
     * @var array 数据库读操作的SQL前缀（前4个字符）
     */
    private $_readSqlPrefix = array(
        'SELE', 'DESC', 'SHOW'
    );

    /**
     * 创建一个 command.
     *
     * @param string $sql
     * @return CDbCommand
     */
    public function createCommand($sql = null)
    {
        if (is_string($sql) && $this->isNeedReadCheck && !$this->isReadOperation($sql)) {
            throw new CDbException('Slave database is readonly! SQL:'.$sql);
        }

        return parent::createCommand($sql);
    }

    /**
     * 打开或关闭数据库连接
     *
     * @param boolean $value whether to open or close DB connection
     * @throws CException if connection fails
     */
    public function setActive($value)
    {
        if ($value != $this->getActive() && $value) {
            $this->setAttribute(PDO::ATTR_TIMEOUT, $this->timeout);
        }

        parent::setActive($value);
    }

    /**
     * Returns the currently active transaction.
     *
     * @return null
     */
    public function getCurrentTransaction()
    {
        return null;
    }

    /**
     * Starts a transaction.
     *
     * @throw CDbException
     */
    public function beginTransaction()
    {
        throw new CDbException('Can\'t begin transaction on slave database.');
    }

    /**
     * 是否为Read操作
     *
     * @param string $sql SQL语句
     *
     * @return bool
     */
    private function isReadOperation($sql)
    {
        $sqlPrefix = strtoupper(substr(ltrim($sql), 0, 4));
        foreach ($this->_readSqlPrefix as $prefix) {
            if ($sqlPrefix == $prefix) {
                return true;
            }
        }

        return false;
    }
}