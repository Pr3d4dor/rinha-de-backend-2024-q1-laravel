<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function getCustomer(int $customerId): ?object
    {
        return DB::table('clientes')
            ->where('id', $customerId)
            ->first();
    }

    public function getLastTransactions(int $customerId, int $limit = 10): array
    {
        return DB::table('transacoes')
            ->select(['valor', 'tipo', 'descricao', 'realizada_em'])
            ->where('cliente_id', $customerId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'valor' => intval($row->valor),
                    'tipo' => $row->tipo,
                    'descricao' => $row->descricao,
                    'realizada_em' => Carbon::parse($row->realizada_em)->format('Y-m-d\TH:i:s\Z'),
                ];
            })
            ->toArray();
    }

    public function create(
        int $customerId,
        int $amount,
        string $type,
        string $description
    ): bool {
        return DB::table('transacoes')
            ->insert([
                'cliente_id' => $customerId,
                'valor' => $amount,
                'tipo' => $type,
                'descricao' => $description,
            ]);
    }
}
