<?php
namespace App;

use App\Exceptions\MessageException;

class Handler
{
    protected $types;
    protected $message;

    public function __construct(string $string)
    {
        $files = array_diff(scandir(__DIR__ . '/Messages/'), ['..', '.']);
        foreach ($files as $file) {
            $class = 'App\\Messages\\' . substr($file, 0, -4);
            $this->types[$class::TYPE] = $class::NAME;
        }
        $this->checkType($string);
    }

    public function run()
    {
        if ($this->message) {
            $class = 'App\\Messages\\' . $this->types[$this->message->type];
            $controller = new $class($this->message);
            return $controller->apply();
        }
        return false;
    }

    protected function checkType($string)
    {
        try {
            $this->message = json_decode($string, false, $depth = 512, JSON_THROW_ON_ERROR);
            if (!in_array($this->message->type??false, array_keys($this->types))) {
                throw new MessageException('The message type is not set or does not match the allowed: ' . implode(', ', array_keys($this->types)));
            }
        } catch (MessageException $e) {
            $this->message = false;
            error_log($e->getMessage());
        } catch (\JsonException $e) {
            $this->message = false;
            error_log("Invalid json error " . $e->getMessage() . " for message:" . $string);
        }
    }
}