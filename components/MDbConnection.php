<?php
/**
 * 扩展后支持主从的数据库操作类
 *
 * Read More: http://devtoby.github.io/yii-db-read-write-splitting
 *
 * File: MDbConnection.php
 * Date: 14-6-14
 * Author: Toby<quflylong@qq.com>
 */

class MDbConnection extends CDbConnection
{
    /**
     * @var int 连接数据库超时时间
     */
    public $timeout = 3;

    /**
     * @var array 从库配置数组
     * @example array( array('connectionString'=>'mysql://<slave01>'), array('connectionString'=>'mysql://<slave02>'),...)
     */
    public $slaves = array();

    /**
     * @var bool 是否开启从库自动继承主库的部分属性
     */
    public $isAutoExtendsProperty  = true;

    /**
     * @var bool 强制使用主库
     */
    private $_forceUseMaster = false;

    /**
     * @var MDbConnection
     */
    private $_slave;

    /**
     * @var array 从库自动继承主库的属性
     */
    private $_autoExtendsProperty = array(
        'username', 'password', 'charset', 'tablePrefix', 'timeout', 'emulatePrepare', 'enableParamLogging',
    );

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
        if (
            !$this->_forceUseMaster && $this->slaves && is_string($sql) && !$this->getCurrentTransaction()
            && $this->isReadOperation($sql) && ($slave = $this->getSlave())
        ) {
            return $slave->createCommand($sql);
        }

        return parent::createCommand($sql);
    }

    /**
     * 强制使用Master，为避免主库过大压力，请随用随关
     * 【注意】除非你有足够的理由，否则请勿使用
     *
     * @param bool $value
     */
    public function forceUseMaster($value = false)
    {
        $this->_forceUseMaster = $value;
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
     * 获取从库连接
     *
     * @return MDbSlaveConnection
     */
    private function getSlave()
    {
        if (!$this->_slave && $this->slaves && is_array($this->slaves)) {
            shuffle($this->slaves);

            foreach ($this->slaves as $slaveConfig) {
                if ($this->isAutoExtendsProperty) {
                    // 自动属性继承
                    foreach ($this->_autoExtendsProperty as $property) {
                        isset($slaveConfig[$property]) || $slaveConfig[$property] = $this->$property;
                    }
                }

                $slaveConfig['class'] = 'MDbSlaveConnection';
                $slaveConfig['autoConnect'] = false;
                $slaveConfig['isNeedReadCheck'] = false; // 因为在路由时已经检查过了

                try {
                    $slave = Yii::createComponent($slaveConfig);

                    $slave->setAttribute(PDO::ATTR_TIMEOUT, $this->timeout);
                    $slave->setActive(true);

                    $this->_slave = $slave;
                    break;
                } catch (Exception $e) {
                    Yii::log("Slave database connection failed! Connection string:{$slaveConfig['connectionString']}", 'warning');
                }
            }
        }

        return $this->_slave;
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