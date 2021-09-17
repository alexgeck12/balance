<?php
namespace App\Models;

use App\Core\Model;

class Balance extends Model
{
    public $table = 'balance';

    public function getByUser($user_id): ? array
    {
       $result = $this->db->getRowByKeys($this->table, ['where' => ['user_id', '=', $user_id]]);

       if (!empty($result)) {
           return $result;
       }

       return null;
    }

    public function update($user_id, $balance): int
    {
        return $this->db->update($this->table, ['balance' => $balance], [
            ['user_id', '=', $user_id], ['blocked', '=', '0']
        ]);
    }
}