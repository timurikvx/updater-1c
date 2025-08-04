<?php

namespace Timurikvx\Update1c;

use RacWorker\Entity\ClusterEntity;
use RacWorker\Entity\InfobaseEntity;
use RacWorker\RacArchitecture;
use RacWorker\RacWorker;
use RacWorker\Services\ClusterAgent;
use RacWorker\Services\ClusterUser;
use RacWorker\Services\InfobaseUser;
use Timurikvx\Update1c\Command\Command;

class Updater
{

    private string $version;

    private bool $isLinux;

    private string $cluster_name;

    private string $infobase_name;

    private InfobaseUser $user;

    private ClusterAgent $agent;

    private ClusterUser $admin;

    private RacWorker $worker;

    private ClusterEntity|null $cluster = null;

    private InfobaseEntity|null $infobase = null;

    private string $code;

    private bool $is32bits;

    private StorageConnection $storage;

    private array $changes = [];

    private string $ID = '';

    private string $infobase_login = '';

    private string $infobase_password = '';

    public function __construct(string $version, string $host, int $port, bool $isLinux = false, bool $is32bits = true)
    {
        $this->version = $version;
        $architecture = ($is32bits)? RacArchitecture::X86_64: RacArchitecture::X64;
        $this->worker = new RacWorker($version, $host, $port, $architecture);
        $this->is32bits = $is32bits;
        $this->isLinux = $isLinux;
        $this->code = '';
        $this->init();
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
        $this->updatePermissionCode();
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
            $this->stopSchedules();
            $this->stopSessions();
            $this->removeConnections();
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

    public function setCurrentID(string $id): void
    {
        $this->ID = $id;
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

    /**
     * @throws \Exception
     */
    public function stopSchedules(): bool
    {
        return $this->setScheduledJobsDeny(true);
    }

    /**
     * @throws \Exception
     */
    public function stopSessions(): bool
    {
        return $this->setSessionsDeny(true);
    }

    /**
     * @throws \Exception
     */
    public function startSessions(): bool
    {
        return $this->setSessionsDeny(false);
    }

    /**
     * @throws \Exception
     */
    public function startSchedules(): bool
    {
        return $this->setScheduledJobsDeny(false);
    }

    /**
     * @throws \Exception
     */
    public function removeConnections(): void
    {
        $error = '';
        $sessions = $this->worker->session->list($this->cluster, $this->infobase, $error);
        $this->handleError($error, 115);
        $servers = $this->worker->server->list($this->cluster, $error);
        $this->handleError($error, 116);
        foreach($servers as $server){
            $processes = $this->worker->process->list($this->cluster, $server, $error);
            $this->handleError($error, 117);
            foreach($processes as $process){
                $connections = $this->worker->connection->list($this->cluster, $process, $this->infobase, $error);
                $this->handleError($error, 118);
                foreach($connections as $connection){
                    $this->worker->connection->remove($this->cluster, $process, $connection, $this->infobase->getInfobaseUser());
                    //$this->handleError($error, 119);
                }
            }
        }
        foreach($sessions as $session){
            $this->worker->session->remove($this->cluster, $session);
            //$this->handleError($error, 120);
        }
    }

    ///////////////////// PRIVATE /////////////////////

    private function init(): void
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
            $this->startSchedules();
            $this->startSessions();
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

    private function getAccessCode(): string
    {
        if(empty($this->code)){
            return '';
        }
        return ' /UC '.$this->code;
    }

    /**
     * @throws \Exception
     */
    private function updatingFromStorage(): array
    {
        if(empty($this->storage)){
            return [];
        }

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

    private function connection(): string
    {
        $filler = $this->isLinux? '': '"';
        $display = $this->isLinux? 'xvfb-run ': '';
        $path = $this->getPath();
        return $display.$filler.$path.'/1cv8'.$filler;
    }

    private function infobase(): string
    {
        $server = $this->cluster->getHost();
        $base = $this->infobase->getName();
        $user = $this->infobase_login;      //'OбменРИБ';
        $pass = $this->infobase_password;   //'123';
        return "/S \"{$server}\\{$base}\" /N \"{$user}\" /P \"{$pass}\"";
    }

    private function disableMessages(): string
    {
        return " /DisableStartupMessages /DisableSplash /DisableStartupDialogs /DisableUnrecoverableErrorMessage";
    }

    private function getPath(): string
    {
        if($this->isLinux){
            $start = '/opt/1cv8/';
            $bits = ($this->is32bits)? 'x86_64': 'x64';
            $path = $start.$bits.'/'.$this->version;
        }else{
            $start = 'C:\\';
            $bits = ($this->is32bits)? 'Program Files (x86)': 'Program Files';
            $path = $start.$bits.'\\1cv8\\'.$this->version.'\\bin';
        }
        return $path;
    }

    private function getArray(string $filename, &$text = ''): array
    {
        $text = $this->getOutText($filename);
        return array_filter(explode("\r\n", trim($text)));
    }

    private function getOutText(string $filename): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', trim(file_get_contents($filename)));
    }

    /**
     * @throws \Exception
     */
    private function handleUpdate(int $code, string $filename): bool
    {
        $text = '';
        $result = $this->getArray($filename, $text);
        if(count($result) == 0 && $code == 0){
            return true;
        }
        if($code != 0){
            throw new \Exception($text, 177);
        }
        $complete = in_array('Обновление конфигурации успешно завершено', $result);
        if(!$complete){
            $complete = in_array('Конфигурация обновлена динамически', $result);
        }
        return $complete;
    }

    /**
     * @throws \Exception
     */
    private function handleUpdateStorage(int $code, string $filename): array
    {
        $text = '';
        $array = $this->getArray($filename, $text);
        if(count($array) == 0 && $code == 0){
            return [];
        }
        if($code != 0){
            throw new \Exception($text, 177);
        }
        $objects = [];
        $writing = false;
        foreach($array as $line){
            if (str_contains($line, 'Начало операции с хранилищем конфигурации')){
                $writing = true;
                continue;
            }
            if (str_contains($line, 'Операция с хранилищем конфигурации завершена')){
                break;
            }
            if($writing){
                $objects[] = trim(str_replace('Объект получен из хранилища:', '', $line));
            }
        }
        return $objects;
    }

    /**
     * @throws \Exception
     */
    private function prepare(): void
    {
        $this->checkData();
        $this->getCluster();
        $this->getInfobase();
    }

    /**
     * @throws \Exception
     */
    private function setScheduledJobsDeny(bool $value): bool
    {
        $error = '';
        $this->handleError($error, 109);
        $this->infobase->setScheduledJobsDeny($value);
        $result = $this->worker->infobase->update($this->cluster, $this->infobase, $error);
        $this->handleError($error, 110);
        return $result;
    }

    /**
     * @throws \Exception
     */
    private function setSessionsDeny(bool $value): bool
    {
        $error = '';
        $this->infobase->setSessionsDeny($value);
        $result = $this->worker->infobase->update($this->cluster, $this->infobase, $error);
        if(!empty($error)){
            throw new \Exception($error, 107);
        }
        return $result;
    }

    /**
     * @throws \Exception
     */
    private function updatePermissionCode(): void
    {
        if(empty($this->code)){
            return;
        }
        $this->infobase->setPermissionCode($this->code);
        $this->worker->infobase->update($this->cluster, $this->infobase, $error);
        if(!empty($error)){
            throw new \Exception($error, 134);
        }
    }

    /**
     * @throws \Exception
     */
    private function handleError(string $error, int $code = 100): void
    {
        if(!empty($error)){
            throw new \Exception($error, $code);
        }
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
    private function getInfobase(): void
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