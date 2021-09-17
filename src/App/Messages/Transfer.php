<?php
namespace App\Messages;

use App\Models;
use App\Core;

class Transfer extends \App\Message
{
    public $message;

    public const NAME = 'Transfer';
    public const TYPE = 'transfer';

    public function __construct($message)
    {
        parent::__construct($message, ['from_user_id', 'to_user_id', 'balance']);
        $this->message = $this->getMessage();
    }

    public function apply(): ?int
    {
        $balance = new Models\Balance();
        $from_user_id = $balance->getByUser($this->message->data->from_user_id);
        $to_user_id = $balance->getByUser($this->message->data->to_user_id);

        if ((isset($from_user_id) && $from_user_id['blocked'] === 0) &&
            (isset($to_user_id) && $to_user_id['blocked'] === 0)) {

            $minuend = Core\Helpers::convertToInt($from_user_id['balance']);
            $subtrahend = Core\Helpers::convertToInt($this->message->data->balance);
            $difference = $minuend - $subtrahend;

            if ($difference >= 0) {
                $events = new Models\Events();
                $uuid = Core\Helpers::generateUuid();

                $writeOff = $balance->update($this->message->data->from_user_id, Core\Helpers::convertToDecimal($difference));
                if ($writeOff) {
                    $events->add($this->message->data->from_user_id, $this->message->data->balance, self::TYPE, $uuid);
                }

                $term = Core\Helpers::convertToInt($to_user_id['balance']);
                $term2 = Core\Helpers::convertToInt($this->message->data->balance);
                $sum = $term + $term2;

                $enrollment = $balance->update($this->message->data->to_user_id, Core\Helpers::convertToDecimal($sum));
                if ($enrollment) {
                    $events->add($this->message->data->to_user_id, $this->message->data->balance, self::TYPE, $uuid);
                }

                return 1;
            }
        }
        return null;
    }
}