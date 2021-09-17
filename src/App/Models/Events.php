<?php
namespace App\Models;

use App\Core\Model;

class Events extends Model
{
    public $table = 'events';

    public function add($user_id, $sum, $type, $uuid)
    {
        return $this->db->insert($this->table, ['user_id' => $user_id, 'sum' => $sum, 'type' => $type, 'uuid' => $uuid]);
    }
}