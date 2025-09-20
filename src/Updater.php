<?php

namespace Timurikvx\Update1c;

use RacWorker\RacArchitecture;
use RacWorker\RacWorker;
use RacWorker\Services\ClusterAgent;
use RacWorker\Services\ClusterUser;
use RacWorker\Services\InfobaseUser;
use Timurikvx\Update1c\Command\Command;
use Timurikvx\Update1c\Traits\ErrorHandler;
use Timurikvx\Update1c\Traits\Output;
use Timurikvx\Update1c\Traits\PlatformConnection;

class Updater
{

    use ErrorHandler, Output, PlatformConnection;

    private string $cluster_name;

    private string $infobase_name;

    private InfobaseUser $user;

    private ClusterAgent $agent;

    private ClusterUser $admin;

    private RacWorker $worker;

    private string $code;

    private StorageConnection $storage;

    private array $changes = [];

    private string $ID = '';

    public Schedules $schedules;

    public Sessions $sessions;

    public Connections $connections;

    public function __construct(string $version, string $host, int $port, bool $isLinux = false, bool $is32bits = true)
    {
        $this->version = $version;
        $architecture = ($is32bits)? RacArchitecture::X86_64: RacArchitecture::X64;
        $this->worker = new RacWorker($version, $host, $port, $architecture);
        $this->is32bits = $is32bits;
        $this->isLinux = $isLinux;
        $this->code = '';
        $this->initAuth();
    }

    public function setRacWorker(RacWorker $worker): void
    {
        $this->worker = $worker;
    }

    public function setStorageConnection(StorageConnection $storage): void
    {
        $this->storage = $storage;
    }

    /**
     * @throws \Exception
     */
    public function setInfobaseData(string $cluster_name, string $infobase_name): void
    {
        $this->cluster_name = $cluster_name;
        $this->infobase_name = $infobase_name;
        $this->prepare();
    }

    public function setAgentAuth(string $login, string $password = ''): void
    {
        $this->agent = new ClusterAgent($login, $password);
    }

    public function setInfobaseAuth(string $login = '', string $password = ''): void
    {
        $this->user = new InfobaseUser($login, $password);
        $this->infobase_login = $login;
        $this->infobase_password = $password;
    }

    public function setClusterAuth(string $login = '', string $password = ''): void
    {
        $this->admin = new ClusterUser($login, $password);
    }

    public function setPermissionCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @throws \Exception
     */
    public function updateFromStorage(): void
    {
        if(empty($this->storage)){
            return;
        }
        //$this->updatePermissionCode();
        $this->changes = $this->updatingFromStorage();
    }
    /**
     * @throws \Exception
     */
    public function update(int $maxTimeSeconds = 500, bool $dynamic = false, &$message = ''): bool
    {
        set_time_limit($maxTimeSeconds);
        $this->changes = [];

        if (!$dynamic) {
            $this->sessions->off();
            $this->schedules->off();
            $this->connections->clearAll();
            $this->checkDatabase();
        }
        if ($this->ID == $this->getID()){
            $message = 'Базе данных '.$this->infobase->getName().' не требуется обновление';
            return true;
        }
        try {
            $result = $this->updating();
        }catch (\Exception $exception){ //throwable
            $this->afterUpdate($dynamic);
            throw $exception;
        }
        $this->afterUpdate($dynamic);
        $this->ID = $this->getID();
        if($result){
            $message = 'База данных '.$this->infobase->getName().' успешно обновлена';
        }
        return $result;
    }

    /**
     * @throws \Exception
     */
    public function checkDatabase(): void
    {
        $connection = $this->connection();
        $infobase = $this->infobase();
        $messages = $this->disableMessages();
        $storage = '';
        if(!empty($this->storage)){
            $storage = $this->storage->getAuth();
        }

        $filename = __DIR__."/output.txt";
        file_put_contents($filename, '');

        $command = $connection." DESIGNER ".$infobase.$storage.$this->getAccessCode()." /L ru /CheckConfig -ConfigLogIntegrity /Out \"".$filename."\"".$messages;
        $code = 0;
        Command::run($command, $code);

        $result = $this->getOutText($filename);
        if(str_contains($result, 'Ошибок не обнаружено')){
            return;
        }
        throw new \Exception($result, 300);
    }

    public function getID(): string
    {
        $connection = $this->connection();
        $infobase = $this->infobase();
        $messages = $this->disableMessages();

        $filename = __DIR__."/output.txt";
        file_put_contents($filename, '');

        $command = $connection." DESIGNER ".$infobase.$this->getAccessCode()." /L ru /GetConfigGenerationID /Out \"".$filename."\"".$messages;
        $code = 0;
        Command::run($command, $code);
        return $this->getOutText($filename);
    }

    public function getChangedObjects(): array
    {
        return $this->changes;
    }


    ///////////////////// PRIVATE /////////////////////

    private function initAuth(): void
    {
        $this->admin = new ClusterUser('', '');
        $this->user = new InfobaseUser('', '');
        $this->agent = new ClusterAgent('', '');
    }

    /**
     * @throws \Exception
     */
    private function afterUpdate(bool $dynamic): void
    {
        if(!$dynamic){
            $this->sessions->on();
            $this->schedules->on();
        }
    }

    /**
     * @throws \Exception
     */
    private function updating(): bool
    {
        $connection = $this->connection();
        $infobase = $this->infobase();
        $messages = $this->disableMessages();

        $filename = __DIR__."/output.txt";
        file_put_contents($filename, '');

        $command = $connection." DESIGNER ".$infobase.$this->getAccessCode()." /L ru /UpdateDBCfg -Dynamic+ /Out \"".$filename."\"".$messages;
        $code = 0;
        Command::run($command, $code);
        return $this->handleUpdate($code, $filename);
    }

    /**
     * @throws \Exception
     */
    private function updatingFromStorage(): array
    {
        if(empty($this->storage)){
            return [];
        }

        $this->connections->clearConfigurationConnect();

        $connection = $this->connection();
        $infobase = $this->infobase();
        $messages = $this->disableMessages();

        $filename = __DIR__."/output.txt";
        file_put_contents($filename, '');

        $storage = $this->storage->getConnection();

        $command = $connection." DESIGNER ".$infobase.$this->getAccessCode()." /L ru ".$storage." /Out \"".$filename."\"".$messages;
        $code = 0;
        Command::run($command, $code);
        return $this->handleUpdateStorage($code, $filename);
    }

    /**
     * @throws \Exception
     */
    private function prepare(): void
    {
        $this->checkData();
        $this->getCluster();
        $this->initInfobase();
        $this->schedules = new Schedules($this->worker, $this->cluster, $this->infobase);
        $this->sessions = new Sessions($this->worker, $this->cluster, $this->infobase);
        $this->connections = new Connections($this->worker, $this->cluster, $this->infobase);
    }

    /**
     * @throws \Exception
     */
    private function getCluster(): void
    {
        if($this->cluster != null){
            return;
        }
        $error = '';
        $cluster = $this->worker->cluster->getByName($this->cluster_name, $error);
        if(!empty($error)){
            throw new \Exception($error, 101);
        }
        if(is_null($cluster)){
            throw new \Exception('Кластер c именем "'.$this->cluster_name.'" не найден', 102);
        }
        $cluster->setUser($this->admin);
        $cluster->setAgent($this->agent);
        $this->cluster = $cluster;
    }

    /**
     * @throws \Exception
     */
    private function initInfobase(): void
    {
        $error = '';
        if($this->infobase != null){
            return;
        }
        $infobase = $this->worker->infobase->getByName($this->infobase_name, $this->cluster, $error);
        if(!empty($error)){
            throw new \Exception($error, 103);
        }
        if(is_null($infobase)){
            throw new \Exception('База данных не найдена', 104);
        }

        //$uuid = $infobase->uuid().$this->cluster->uuid();

        $error = '';
        $infobase->setUser($this->user);
        $infobaseEntity = $this->worker->infobase->info($this->cluster, $infobase, $error);
        if(!empty($error)){
            throw new \Exception($error, 107);
        }
        $infobaseEntity->setUser($this->user);
        $infobaseEntity->setPermissionCode($this->code);
        $this->infobase = $infobaseEntity;
    }

    /**
     * @throws \Exception
     */
    private function checkData(): void
    {
        if(empty($this->cluster_name)){
            throw new \Exception('Не установлено имя кластера. Выполните функцию "setInfobaseData"');
        }
        if(empty($this->infobase_name)){
            throw new \Exception('Не установлено имя базы данных. Выполните функцию "setInfobaseData"');
        }
    }

}