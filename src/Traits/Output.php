<?php

namespace Timurikvx\Update1c\Traits;

trait Output
{
    protected function getArray(string $filename, &$text = ''): array
    {
        $text = $this->getOutText($filename);
        return array_filter(explode("\r\n", trim($text)));
    }

    protected function getOutText(string $filename): string
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
            return false;
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
}