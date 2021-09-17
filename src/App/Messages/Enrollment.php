<?php
namespace App\Messages;

use App\Models;
use App\Core;

class Enrollment extends \App\Message
{
    public $message;

    public const NAME = 'Enrollment';
    public const TYPE = 'enrollment';

    public function __construct($message)
    {
        parent::__construct($message, ['user_id', 'balance', 'timestamp']);
        $this->message = $this->getMessage();
    }

    public function apply(): ?int
    {
        $balance = new Models\Balance();
        $user_balance = $balance->getByUser($this->message->data->user_id);
        if (isset($user_balance) && $user_balance['blocked'] === 0) {
            $term = Core\Helpers::convertToInt($user_balance['balance']);
            $term2 = Core\Helpers::convertToInt($this->message->data->balance);
            $sum = $term + $term2;

            $result = $balance->update($this->message->data->user_id, Core\Helpers::convertToDecimal($sum));
            if ($result) {
                $events = new Models\Events();
                $events->add($this->message->data->user_id, $this->message->data->balance, self::TYPE, Core\Helpers::generateUuid());
            }
            return $result;
        }
        return null;
    }
}