<?php
namespace App;

use App\Exceptions\MessageException;

abstract class Message
{
    protected $message;

    public function __construct($message, array $fields = [])
    {
        $this->validate($this->message = $message, $fields);
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function validate($message, $fields)
    {
        try {
            if (empty($message->data)) {
                throw new MessageException('The message must have a not empty data');
            }
            foreach ($fields as $field) {
                if (!isset($message->data->{$field})) {
                    throw new MessageException('The message type `' . $message->type . '` must have all fields: ' . implode(', ', $fields));
                }
            }
        } catch (MessageException $e) {
            $this->message = false;
            error_log($e->getMessage());
        }
    }
}