<?php

namespace Itb\Mpgu\Form\mgik\mgd;

use TaskManager;
use Itb\Mpgu\Loggers\LoggerPool;
use Itb\Mpgu\Lib\Config\PoolConfig;

abstract class TaskRequest
{
    /** @var array  */
    public $app;

    /** @var array  */
    public $client;

    /** @var array  */
    public $fields;

    /** @var array */
    protected $profile;

    /** @var array */
    protected $config;

    /** @var string */
    protected $plugin = 'mgd2019_serviceSend';

    /**
     * @param array $client
     * @param array $fields
     * @param array $app
     */
    public function __construct(array $client, array $fields = [], array $app = [])
    {
        $this->app = $app;
        $this->client = $client;
        $this->fields = $fields;

        $this->config = PoolConfig::me()->conf('Mgik')->get('amqp');
    }

    /**
     * @param array $profile
     */
    public function setProfile(array $profile = [])
    {
        $this->profile = $profile;
    }

    /**
     * @return string
     */
    abstract public function queueName();

    /**
     * @return array
     */
    abstract public function asArray();

    /**
     * @return string
     */
    public function asJson()
    {
        return json_encode($this->asArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array
     */
    public function asTaskData()
    {
        return [
            'eno' => $this->app['REG_NUM'] ?? null,
            'queue' => $this->queueName(),
            'json' => $this->asJson(),
        ];
    }

    /**
     * Добавляет задачу в TaskManager
     * @return void
     */
    public function addQueueTask()
    {
        $result = TaskManager::queueTask(
            $this->client['PGU_USER_ID'],
            $this->plugin,
            $this->asTaskData(),
            [
                'execute_now' => true,
                'store_in_buffer' => false,
                'app_id' => $this->app['APP_ID'] ?? null,
            ]
        );

        if (! empty($result) && $result != 'OK') {
            $logger = LoggerPool::create('MgicTaskManager ['. CFG_HOST_NAME .']', 'graylog');
            $logger->error('Не удалось отправить заявку', [
                'result' => $result,
                'action' => $this->plugin,
                'user_id' => $this->client['PGU_USER_ID'],
                'app_id' => $this->app['APP_ID'] ?? null,
                'eno' => $this->app['REG_NUM'] ?? null,
                'data' => $this->asJson(),
            ]);
        }
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function field($name, $default = null)
    {
        $value = $this->fields[$name] ?? null;

        return $value ? $value : $default;
    }

    /**
     * @param string $date Дата
     * @param string $format Формат даты. По умолчанию 'd.m.Y'
     * @return string|null Дата в формате 'Y-m-d H:i:s.000'
     */
    protected function formatAsDate($date = '', $format = 'd.m.Y')
    {
        $dt = \DateTime::createFromFormat($format, $date);

        if (! $dt) {
            return null;
        }

        if (str_replace(['H', 'i', 's'], '', $format) === $format) {
            $dt->setTime(0, 0, 0);
        }

        return $dt->format('Y-m-d H:i:s.000');
    }

    /**
     * @param $string
     * @return string|null
     */
    protected function formatAsNumber($string = '')
    {
        $result = preg_replace('/[^0-9]/', '', $string);

        return $result ? $result : null;
    }

    /**
     * @param string $serial_number
     * @return string|null
     */
    protected function formatAsPassportSerial($serial_number = '')
    {
        $result = substr($this->formatAsNumber($serial_number), 0, 4);

        return $result ? $result : null;
    }

    /**
     * @param string $serial_number
     * @return string|null
     */
    protected function formatAsPassportNumber($serial_number = '')
    {
        $result = substr($this->formatAsNumber($serial_number), 4, 6);

        return $result ? $result : null;
    }

}